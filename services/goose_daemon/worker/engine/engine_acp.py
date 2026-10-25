#!/usr/bin/env python3
"""
engine_acp.py

我們 ↔ goose ACP 嘅接駁（單一職責：組連線參數＋驅動一輪 ACP 查詢嘅回呼）。

上游：engine_entry.execute_job（prepare_session → run_session）
下游：acp_client（協定收發）、msg/message_context（檔案契約）、worker/message_events（事件詞彙）

分工：acp_client＝純協定（唔識 msg_conf）；本檔＝我們契約（thinking／text 分流、權限卡、狀態事件）。
"""

import asyncio
import base64
import json
import os
import time
from pathlib import Path

from ...context import (
    acp_client,
    message_log,
    message_status,
    message_store,
)
from ...msg import message_context
from ..message_events import (
    EVENT_CANCELLATION_REQUESTED,
    EVENT_CONNECTION_ESTABLISHED,
    EVENT_REPLY_STREAM_OPENED,
    EVENT_RESPONSE_COMPLETED,
    EVENT_SUBPROCESS_READY,
    EVENT_TOOL_INVOKED,
)
from . import engine_context
from .acp_client import SessionCallbacks, SessionRequest

# 權限請求等待上限（秒——無人決定即自動拒；本側常數——暫唔 expose 喺 hkai_config.json）
PERMISSION_WAIT_TIMEOUT_SEC = 60


def prepare_session(goose_env: dict) -> SessionRequest:
    """
    ACP 準備：組本次連線參數（附件／prompt／provider／model／session 名）——回 session_request。
    權限檔案契約（message 目錄）：
        msg_permission.json           本側寫（pending 請求——PHP 讀出提示卡）
        msg_permission_decision.json  本側讀（使用者決定；逾時＝本側寫入 deny）
        msg_conf.json                 本側讀（permission_allow_all／permission_deny_all 旗標；PHP 寫——本輪免問）
    """
    # 語境（msg_conf——execute_job 已載入 message_store）
    content            = message_store().get_msg_prompt()
    project_path       = message_store().get_project_path()
    goose_executable   = engine_context.resolve_executable()
    goose_session_name = "hkai-" + message_store().get_chat_sid()

    # goose chat mode（引擎原生模式——會話政策：chat／smart_approve／auto；由 prepare_goose_env 寫入）
    goose_mode = str(goose_env["GOOSE_MODE"])

    # 附件（msg_conf.attachments——原檔轉 base64 交 ACP image block；冇＝空）
    prompt_images = []
    attachment_paths = []

    # 逐個附件讀檔
    for attachment_file_item in message_store().get_msg_conf_data().get("attachments", []):
        attachment_path = str(attachment_file_item.get("path", ""))

        # 判定：檔案唔存在 → 跳過
        if attachment_path == "":
            continue

        try:
            with open(attachment_path, "rb") as attachment_handle:
                attachment_bytes = attachment_handle.read()
        except OSError:
            continue

        prompt_images.append({
            "mime_type":   str(attachment_file_item.get("mime_type", "image/png")),
            "data_base64": base64.b64encode(attachment_bytes).decode("ascii"),
        })

        attachment_paths.append(attachment_path)

    # prompt 文字補附件路徑（image block 之外嘅後備——agent 可自行開檔）
    prompt_text = content

    # 判定：有附件 → 補路徑行
    if len(attachment_paths) > 0:
        prompt_text = prompt_text + "\n\n[附件圖片]\n" + "\n".join(attachment_paths)

    # provider／model（litellm 式 provider/model；model 可再含 "/"）
    model_parts = message_store().get_model_name().partition("/")
    provider_name = model_parts[0]
    model_name = model_parts[2]

    # 判定：自訂 provider（goose 內部名＝custom_<前綴>）→ 轉官方 config 值
    if provider_name in engine_context.CUSTOM_PROVIDER_TABLE:
        provider_name = "custom_" + provider_name

    # 本次連線參數（dict——key 同名變數喺 acp_client 內解包）
    session_request: SessionRequest = {
        "agent_command":    [goose_executable, "acp"],
        "agent_env":        goose_env,
        "project_path":     project_path,
        "session_title":    goose_session_name,
        "prompt_text":      prompt_text,
        "prompt_images":    prompt_images,
        "session_mode":     goose_mode,
        "resume_session":   True,
        "session_history":  None,
        "provider_name":    provider_name,
        "model_name":       model_name,
        "reasoning_effort": message_store().get_reasoning_effort(),
    }

    return session_request


def run_session(session_request: SessionRequest, callbacks: SessionCallbacks) -> None:
    """
    ACP 路徑：跑 event loop（同步阻塞——回呼驅動一切）。

    session_request（dict——本次連線參數）：
        agent_command       agent 子程序命令（list——含引數；goose＝[執行檔, "acp"]）
        agent_env           子程序環境（完整 env）
        project_path        工作目錄（ACP cwd——真實專案根）
        session_title       session 名（每對話一條——同名續話；無續話引擎＝空）
        prompt_text         本輪查詢文字
        prompt_images       圖片（[{mime_type, data_base64}]——無＝空）
        session_mode        session 模式（UI 三模查表值——空＝唔設（session 默認））
        resume_session      續話與否（True＝同名續話；False＝直接開新）
        session_history     隨新 session 帶入嘅歷史（[{role, content}]——resume_session=False 用；無＝None）
        provider_name       provider（自訂＝custom_<前綴>）
        model_name          模型名（provider 之後部分）
        reasoning_effort    思考力度（空＝引擎默認）
    callbacks（dict——事件回呼；除 on_permission_request 為 async 外，其餘同步）：
        on_stdio_line(line)                      原始輸出行（協定追蹤＋stderr——純紀錄）
        on_ready(session_id)                     連線＋session＋模式已備（尚未查詢）
        on_update(update_kind, payload)          session 事件（正規化——見 acp_client 檔頭）
        on_permission_request(tool_call) -> str  async——權限請求（回選項 id）
        on_prompt_finished(prompt_response)      本輪正常結束（PromptResponse）
        on_cancelled()                           使用者取消已測到（終止前記一次）
        is_cancelled() -> bool                   要唔要停？（使用者取消／工單已判失敗——watchdog 每秒問）
    """
    asyncio.run(acp_client().execute_session(session_request, callbacks))


def build_callbacks(session_request: SessionRequest) -> SessionCallbacks:
    """組本次連線回呼（acp_client 事件 → 本側契約——逐支見各方法）。"""
    message_id      = message_store().get_message_id()
    session_label   = str(session_request["session_title"])
    job_stdout_file = message_context.get_msg_file_path(message_store().get_message_dir(), "stdout")

    # 串流翻譯狀態（跨回呼共享——同一輪回應嘅翻譯狀態）
    stream_state = {
        "response_index":       0,
        "current_message_id":   "",
        "first_content_seen":   False,
        "stream_opened_logged": False,
        "usage":                {},
    }

    return {
        "on_stdio_line":         lambda stdio_line: _append_job_stdout(job_stdout_file, stdio_line),
        "on_ready":              lambda session_id: _on_ready(session_label),
        "on_update":             lambda update_kind, update_payload: _on_update(update_kind, update_payload, stream_state),
        "on_permission_request": lambda tool_call: _on_permission_request(tool_call, message_id),
        "on_prompt_finished":    lambda prompt_response: _on_prompt_finished(prompt_response, stream_state),
        "on_cancelled":          lambda: _on_cancelled(),
        "is_cancelled":          lambda: message_store().get_msg_status() in ("cancelled", "failed"),
    }


def _on_ready(session_label: str) -> None:
    """ACP 就緒（連線＋session＋模式已備——未查詢）——報 SUBPROCESS_READY。"""
    message_log().append_info_log(EVENT_SUBPROCESS_READY, "ACP session ready: " + session_label)
    message_status().apply(EVENT_SUBPROCESS_READY)


def _on_update(update_kind: str, update_payload: dict, stream_state: dict) -> None:
    """
    ACP 事件 → 契約輸出：
        thought → {i}.thinking 檔；text → {i}.result 檔（首段記串流開啟）；replay → {i}.replay 檔（load 重播——獨立歸檔，唔屬本輪回應）；
        tool → TOOL_INVOKED 事件；usage → 暫存（本輪完結時記）；other → 免記（協定追蹤已留痕）。
    段落 index：_meta.messageId 換新（上一輪已有內容）即前進——同訊息 id 語義。
    """
    # 引擎重播段（load 重播——唔屬本輪回應；獨立歸檔；唔觸發連通／段 index 前進）
    if update_kind == "replay":
        message_log().append_response_replay(stream_state["response_index"], update_payload.get("text", ""))
        return

    message_id = update_payload.get("message_id", "")

    # 首個內容（thought／text）→ 連通（replying——只發一次）
    is_content_update = update_kind in ("thought", "text")
    if is_content_update and stream_state["first_content_seen"] is False:
        stream_state["first_content_seen"] = True
        message_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "goose output arrived")
        message_status().apply(EVENT_CONNECTION_ESTABLISHED)

    # 判定：新 message id（上一輪已有內容）→ 段落 index 前進（每輪一對檔）
    if message_id != "" and stream_state["current_message_id"] != "" and message_id != stream_state["current_message_id"]:
        stream_state["response_index"] += 1

    # 判定：本輪 message id → 記為當前
    if message_id != "":
        stream_state["current_message_id"] = message_id

    # 思考段（reasoning——入 {i}.thinking 檔）
    if update_kind == "thought":
        message_log().append_response_thinking(stream_state["response_index"], update_payload.get("text", ""))
        return

    # 主回答段（入 {i}.result 檔；首段記串流開啟）
    if update_kind == "text":
        # 判定：尚未記串流開啟 → 記一次（trace）
        if stream_state["stream_opened_logged"] is False:
            stream_state["stream_opened_logged"] = True
            message_log().append_info_log(EVENT_REPLY_STREAM_OPENED, "goose reply stream opened")
        message_log().append_response_result(stream_state["response_index"], update_payload.get("text", ""))
        return

    # 工具活動（TOOL_INVOKED 事件——trace）
    if update_kind == "tool":
        message_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "ACP tool: " + update_payload.get("title", ""),
            {"tool_call_id": update_payload.get("tool_call_id", "")},
        )
        return

    # 用量更新（暫存——本輪完結時同完成事件一齊記）
    if update_kind == "usage":
        stream_state["usage"] = update_payload


async def _on_permission_request(tool_call, message_id: str) -> str:
    """
    ACP 權限請求（async——acp_client await 本支）：寫 pending 請求 → 等決定
    （上限 PERMISSION_WAIT_TIMEOUT_SEC 秒、每 PERMISSION_DECISION_POLL_INTERVAL_SEC 秒查一次）
    → 回選項 id（allow_once／reject_once）；逾時＝本側代寫 deny 決定。
    本輪已「允許全部」（msg_conf 旗標）＝免問：唔寫 pending、直接回 allow_once。
    本輪已「拒絕全部」（msg_conf 旗標）＝免問：唔寫 pending、直接回 reject_once。
    """
    message_dir = message_store().get_message_dir()

    permission_request_file  = message_context.get_msg_file_path(message_dir, "permission")
    permission_decision_file = message_context.get_msg_file_path(message_dir, "permission_decision")

    request_id = str(getattr(tool_call, "tool_call_id", "") or "")
    tool_title = str(getattr(tool_call, "title", "") or "")
    tool_input = getattr(tool_call, "raw_input", None)

    # 判定：本輪已允許全部（msg_conf 旗標）→ 直接放行（唔出卡）
    if _is_permission_allow_all(message_id):
        message_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "Permission auto-allowed (allow all for this message)",
            {"request_id": request_id, "tool_title": tool_title},
        )
        return "allow_once"

    # 判定：本輪已拒絕全部（msg_conf 旗標）→ 直接拒絕（唔出卡）
    if _is_permission_deny_all(message_id):
        message_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "Permission auto-denied (deny all for this message)",
            {"request_id": request_id, "tool_title": tool_title},
        )
        return "reject_once"

    # 上一請求殘留先清（request_id 核對係第二重保險——PHP 側亦核）
    _remove_file_if_exists(permission_decision_file)

    # 寫 pending 請求（原子——PHP 讀到嘅永遠係完整 JSON）
    deadline_ts = int(time.time()) + PERMISSION_WAIT_TIMEOUT_SEC
    permission_request = {
        "request_id":  request_id,
        "tool_title":  tool_title,
        "tool_input":  tool_input if isinstance(tool_input, (dict, list)) else str(tool_input or ""),
        "created_at":  int(time.time()),
        "deadline_ts": deadline_ts,
    }
    _write_json_atomic(permission_request_file, permission_request)
    message_log().append_info_log(
        EVENT_TOOL_INVOKED,
        "Permission requested: " + tool_title,
        {"request_id": request_id, "tool_title": tool_title},
    )

    # 等決定（每 tick 查一次決定檔；逾時＝自動 deny）
    while time.time() < deadline_ts:
        # 判定：使用者已取消 → 當拒絕收（watchdog 同時會終止連線）
        if message_store().get_msg_status() == "cancelled":
            message_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Permission wait cancelled by user")
            return "reject_once"

        decision = _read_permission_decision(permission_decision_file, request_id)

        # 判定：使用者允許 → 回允許
        if decision == "allow":
            message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed by user", {"request_id": request_id})
            return "allow_once"

        # 判定：使用者允許全部（本輪）→ 照放行（後續請求靠 msg_conf 旗標——唔再出卡）
        if decision == "allow_all":
            message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed for this message (allow all)", {"request_id": request_id})
            return "allow_once"

        # 判定：使用者拒絕全部（本輪）→ 照拒（後續請求靠 msg_conf 旗標——唔再出卡）
        if decision == "deny_all":
            message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied for this message (deny all)", {"request_id": request_id})
            return "reject_once"

        # 判定：使用者拒絕 → 回拒絕
        if decision == "deny":
            message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied by user", {"request_id": request_id})
            return "reject_once"

        await asyncio.sleep(engine_context.PERMISSION_DECISION_POLL_INTERVAL_SEC)

    # 逾時（無人決定）→ 本側代寫 deny（紀錄一體）＋回拒絕
    timeout_decision = {
        "request_id": request_id,
        "decision":   "deny",
        "source":     "timeout",
        "decided_at": int(time.time()),
    }
    _write_json_atomic(permission_decision_file, timeout_decision)
    message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission timed out (auto deny)", {"request_id": request_id})

    return "reject_once"


def _read_permission_decision(permission_decision_file: Path, request_id: str) -> str:
    """讀決定檔（request_id 對唔上＝上一請求殘留 → 當未決；缺／壞 → 空字串）。"""
    try:
        decision_data = json.loads(permission_decision_file.read_text(encoding="utf-8"))
    except Exception:  # noqa: BLE001
        return ""

    # 判定：非 dict → 未決
    if not isinstance(decision_data, dict):
        return ""

    # 判定：request_id 唔對（殘留）→ 未決
    if str(decision_data.get("request_id", "")) != request_id:
        return ""

    return str(decision_data.get("decision", ""))


def _is_permission_allow_all(message_id: str) -> bool:
    """本輪已允許全部？（讀 msg_conf 旗標 permission_allow_all——每次問之前讀硬檔；缺／壞 → False）。"""
    message_dir = message_store().get_message_dir()

    permission_conf_file = message_context.get_msg_file_path(message_dir, "conf")

    try:
        conf_data = json.loads(permission_conf_file.read_text(encoding="utf-8"))
    except Exception:  # noqa: BLE001
        return False

    # 判定：非 dict → 未允許
    if not isinstance(conf_data, dict):
        return False

    return bool(conf_data.get("permission_allow_all", False))


def _is_permission_deny_all(message_id: str) -> bool:
    """本輪已拒絕全部？（讀 msg_conf 旗標 permission_deny_all——每次問之前讀硬檔；缺／壞 → False）。"""
    message_dir = message_store().get_message_dir()

    permission_conf_file = message_context.get_msg_file_path(message_dir, "conf")

    try:
        conf_data = json.loads(permission_conf_file.read_text(encoding="utf-8"))
    except Exception:  # noqa: BLE001
        return False

    # 判定：非 dict → 未拒絕
    if not isinstance(conf_data, dict):
        return False

    return bool(conf_data.get("permission_deny_all", False))


def _write_json_atomic(target_file: Path, json_payload: dict) -> None:
    """原子寫 JSON（tmp＋replace——讀者唔會見到半截檔）。"""
    temporary_file = target_file.with_suffix(target_file.suffix + ".tmp")
    temporary_file.write_text(json.dumps(json_payload, ensure_ascii=False), encoding="utf-8")
    os.replace(temporary_file, target_file)


def _remove_file_if_exists(target_file: Path) -> None:
    """檔案存在即刪（殘留清理——唔存在＝正常）。"""
    try:
        target_file.unlink()
    except FileNotFoundError:
        pass


def _on_prompt_finished(prompt_response, stream_state: dict) -> None:
    """
    本輪結束（PromptResponse）——契約收尾：
    用量記 RESPONSE_COMPLETED（成功事件）；stop_reason＝cancelled 除外（取消路徑唔當完成）。
    """
    stop_reason = str(getattr(prompt_response, "stop_reason", "") or "")

    # 判定：協定報 cancelled（本側曾發取消）→ 唔記完成（收尾交取消路徑）
    if stop_reason == "cancelled":
        message_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "ACP turn cancelled")
        return

    usage = stream_state.get("usage", {})
    usage_metadata = {
        "total_tokens":  usage.get("used", 0),
        "context_size":  usage.get("size", 0),
        "cost_usd":      usage.get("cost_usd", 0),
    }
    message_log().append_success_log(EVENT_RESPONSE_COMPLETED, "ACP stream completed", usage_metadata)
    message_status().apply(EVENT_RESPONSE_COMPLETED)


def _on_cancelled() -> None:
    """使用者取消（watchdog 測到）——記事件一次（終止緊隨其後）。"""
    message_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — stopping goose (ACP)")


def _append_job_stdout(job_stdout_file: Path, raw_line: str) -> None:
    """raw 行 append 寫入 job_stdout（原樣——首寫即建立檔）。"""
    with job_stdout_file.open("a", encoding="utf-8", errors="replace") as output_file:
        output_file.write(raw_line + "\n")

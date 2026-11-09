#!/usr/bin/env python3
"""
session.py

我們 ↔ goose ACP 嘅接駁（單一職責：組連線參數＋驅動一輪 ACP 查詢嘅回呼）。

上游：entry.execute_job（prepare_session → run_session）
下游：acp_client（協定收發）、msg/message_context（檔案契約）、worker/message_events（事件詞彙）

分工：acp_client＝純協定（唔識 msg_conf）；本檔＝我們契約（thinking／text 分流、權限卡、狀態事件）。
"""

import asyncio
import json
import os
import time
from pathlib import Path
from typing import TypedDict, cast

from ...context import (
    acp_client,
    message_log,
    message_status,
    message_store,
)
from ...msg import message_context
from ...utils.timer import timer_class
from ..message_events import (
    EVENT_CANCELLATION_REQUESTED,
    EVENT_CONNECTION_ESTABLISHED,
    EVENT_REPLY_STREAM_OPENED,
    EVENT_RESPONSE_COMPLETED,
    EVENT_SUBPROCESS_READY,
    EVENT_TOOL_INVOKED,
)
from . import paths
from .acp_client import PromptImage, SessionCallbacks, SessionRequest

# 權限請求等待上限（秒——無人決定即自動拒；本側常數——暫唔 expose 喺 hkai_config.json）
PERMISSION_WAIT_TIMEOUT_SEC = 60



class StreamState(TypedDict):
    """本輪串流翻譯狀態（跨回呼共享）。"""

    response_index: int
    current_agent_turn_id: str
    first_content_seen: bool
    stream_opened_logged: bool
    usage: dict[str, object]
    tool_signatures: dict[str, str]


def prepare_session(goose_env: dict[str, str]) -> SessionRequest:
    """
    ACP 準備：組本次連線參數（附件／prompt／provider／model／session 名）——回 session_request。
    權限檔案契約（message 目錄）：
        msg_permission.json           本側寫（pending 請求——PHP 讀出提示卡）
        msg_permission_decision.json  本側讀（使用者決定；逾時＝本側寫入 deny）
        meta.json                     本側讀（permission_allow_all／permission_deny_all 旗標；PHP 寫——本輪免問）
    """
    # 語境（msg_conf——execute_job 已載入 message_store）
    content            = message_store().get_msg_prompt()
    project_path       = message_store().get_project_path()
    goose_executable   = paths.resolve_executable()
    goose_session_name = "hkai-" + message_store().get_chat_sid()

    # goose chat mode（agent原生模式——會話政策：chat／smart_approve／auto；由 prepare_goose_env 寫入）
    goose_mode = str(goose_env["GOOSE_MODE"])

    # 附件（msg_conf.attachments——只列本機路徑；模型需要睇圖時自行呼叫 read_image，唔再 inline base64）
    prompt_images: list[PromptImage] = []
    attachment_lines: list[str] = []

    attachments_conf = cast("list[dict[str, object]]", message_store().get_msg_conf_data().get("attachments", []))
    attachments_root = str(message_store().get_msg_conf_data().get("attachments_dir", "") or "")

    # 逐個附件：砌一行「路徑＋原檔名＋尺寸／大小」（零讀檔——唔佔 provider bytes）
    for attachment_file_item in attachments_conf:
        attachment_path = str(attachment_file_item.get("path", ""))
        attachment_filename = str(attachment_file_item.get("filename", ""))

        # 判定：草稿 meta 只有 filename → 對照成包內相對路徑
        if attachment_path == "":
            # 判定：有檔名 → 砌 attachments/<檔名>
            if attachment_filename != "":
                attachment_path = "attachments/" + attachment_filename

        # 判定：冇路徑 → 跳過
        if attachment_path == "":
            continue

        # 相對路徑 → 以附件實際目錄（meta.attachments_dir）為基準；冇記 → 箱內 attachments/
        if not os.path.isabs(attachment_path):
            attachment_path = os.path.join(attachments_root or str(message_store().get_message_dir() / "attachments"), os.path.basename(attachment_path))

        # 判定：檔案唔存在 → 跳過
        if os.path.isfile(attachment_path) is False:
            continue

        attachment_original_name = str(attachment_file_item.get("original_filename", "") or attachment_filename)
        attachment_size_bytes = int(attachment_file_item.get("file_size", 0) or 0)
        attachment_width = int(attachment_file_item.get("image_width", 0) or 0)
        attachment_height = int(attachment_file_item.get("image_height", 0) or 0)

        attachment_line = attachment_path + " （" + attachment_original_name
        # 判定：有尺寸 → 補寬高
        if attachment_width > 0:
            attachment_line += "，" + str(attachment_width) + "×" + str(attachment_height)
        attachment_line += "，" + str(round(attachment_size_bytes / 1048576, 2)) + " MB）"
        attachment_lines.append(attachment_line)

    # prompt 文字補附件清單（模型自行 read_image——原始 bytes 唔入請求）
    prompt_text = content

    # 判定：有附件 → 補清單同使用指示
    if len(attachment_lines) > 0:
        attachment_header = chr(10) + chr(10) + "[附件圖片（本機檔案；需要睇圖時請呼叫 read_image 工具讀取對應路徑——唔會自動送上）]" + chr(10)
        prompt_text = prompt_text + attachment_header + chr(10).join(attachment_lines)

    # provider／model（litellm 式 provider/model；model 可再含 "/"）
    model_parts = message_store().get_model_name().partition("/")
    provider_name = model_parts[0]
    model_name = model_parts[2]

    # 判定：自訂 provider（goose 內部名＝custom_<前綴>）→ 轉官方 config 值
    if provider_name in paths.CUSTOM_PROVIDER_TABLE:
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
        session_title       session 名（每對話一條——同名續話；無續話agent＝空）
        prompt_text         本輪查詢文字
        prompt_images       圖片（[{mime_type, data_base64}]——無＝空）
        session_mode        session 模式（UI 三模查表值——空＝唔設（session 默認））
        resume_session      續話與否（True＝同名續話；False＝直接開新）
        session_history     隨新 session 帶入嘅歷史（[{role, content}]——resume_session=False 用；無＝None）
        provider_name       provider（自訂＝custom_<前綴>）
        model_name          模型名（provider 之後部分）
        reasoning_effort    思考力度（空＝agent默認）
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
    stream_state: StreamState = {
        "response_index":       0,
        "current_agent_turn_id":   "",
        "first_content_seen":   False,
        "stream_opened_logged": False,
        "usage":                {},
        "tool_signatures":      {},
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
    _ = message_log().append_info_log(EVENT_SUBPROCESS_READY, "ACP session ready: " + session_label)
    _ = message_status().apply(EVENT_SUBPROCESS_READY)


def _on_update(update_kind: str, update_payload: dict[str, object], stream_state: StreamState) -> None:
    """
    ACP 事件 → 契約輸出：
        thought → {i}.thinking 檔；text → {i}.result 檔（首段記串流開啟）；replay → {i}.replay 檔（load 重播——獨立歸檔，唔屬本輪回應）；
        tool → TOOL_INVOKED 事件；usage → 暫存（本輪完結時記）；other → 免記（協定追蹤已留痕）。
    段落 index：_meta.messageId 換新（上一輪已有內容）即前進——同訊息 id 語義。
    """
    # agent重播段（load 重播——唔屬本輪回應；獨立歸檔；唔觸發連通／段 index 前進）
    if update_kind == "replay":
        _ = message_log().append_response_replay(stream_state["response_index"], str(update_payload.get("text", "")))
        return

    agent_turn_id = str(update_payload.get("agent_turn_id", ""))

    # 首個內容（thought／text）→ 連通（replying——只發一次）
    is_content_update = update_kind in ("thought", "text")
    if is_content_update and stream_state["first_content_seen"] is False:
        stream_state["first_content_seen"] = True
        _ = message_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "goose output arrived")
        _ = message_status().apply(EVENT_CONNECTION_ESTABLISHED)

    # 三個條件各自具名（唔壓成一行）
    has_incoming_turn_id = agent_turn_id != ""
    has_previous_turn_id = stream_state["current_agent_turn_id"] != ""
    is_different_turn_id = agent_turn_id != stream_state["current_agent_turn_id"]

    is_new_turn = has_incoming_turn_id

    # 判定：之前未有一輪 → 唔算新一輪
    if has_previous_turn_id is False:
        is_new_turn = False

    # 判定：id 同之前一樣 → 唔算新一輪
    if is_different_turn_id is False:
        is_new_turn = False

    # 判定：新一輪 → 先關上一輪，段落 index 再前進（每輪一對檔）
    if is_new_turn:
        _close_open_tool_calls(stream_state, "completed")

        message_log().close_response_turn(int(stream_state["response_index"]), "completed")

        stream_state["response_index"] += 1

    # 判定：本輪 agent turn id → 記為當前（poll_list 建 entry 時寫入）
    if agent_turn_id != "":
        stream_state["current_agent_turn_id"] = agent_turn_id

    message_log().set_current_agent_turn_id(str(stream_state["current_agent_turn_id"]))

    message_log().remember_agent_turn(
        agent_turn_id,
        int(stream_state["response_index"]),
        update_kind,
        len(str(update_payload.get("text", ""))),
    )

    # 思考段（reasoning——入 {i}.thinking 檔）
    if update_kind == "thought":
        _ = message_log().append_response_thinking(stream_state["response_index"], str(update_payload.get("text", "")))
        return

    # 主回答段（入 {i}.result 檔；首段記串流開啟）
    if update_kind == "text":
        # 判定：尚未記串流開啟 → 記一次（trace）
        if stream_state["stream_opened_logged"] is False:
            stream_state["stream_opened_logged"] = True
            _ = message_log().append_info_log(EVENT_REPLY_STREAM_OPENED, "goose reply stream opened")
        reply_text = str(update_payload.get("text", ""))

        from .. import error_text as _error_text

        # 判定：已進入錯誤模式，或呢一段似錯誤 → 寫入 error detail（唔當正常回覆）
        if stream_state.get("error_mode") is True or _error_text.looks_like_error(reply_text) is True:
            stream_state["error_mode"] = True
            _ = message_log().append_error_detail(reply_text)
            return

        _ = message_log().append_response_result(stream_state["response_index"], reply_text)
        return

    # 工具活動（TOOL_INVOKED 事件——trace；同一工具同狀態只記一次）
    if update_kind == "tool":
        tool_call_id = str(update_payload.get("tool_call_id", ""))
        tool_name = str(update_payload.get("tool_name", ""))
        tool_status = str(update_payload.get("status", ""))
        tool_title = str(update_payload.get("title", ""))

        # 顯示名：工具名優先（冇就用 title）
        display_name = tool_name

        # 判定：冇工具名 → 用 title
        if display_name == "":
            display_name = tool_title

        tool_signature = tool_call_id + "|" + tool_status + "|" + tool_title

        # 判定：同一工具同狀態已記 → 唔重複
        if stream_state["tool_signatures"].get(tool_call_id) == tool_signature:
            return

        stream_state["tool_signatures"][tool_call_id] = tool_signature

        # 工具調用全文記錄（每輪一檔；start／done 兩行，靠 tool_call_id 合併）
        tool_seq_by_id = stream_state.setdefault("tool_seq_by_id", {})

        # 判定：首次見到呢個 id → 派次序（輪內顯示次序）
        if tool_call_id != "" and tool_call_id not in tool_seq_by_id:
            tool_seq_by_id[tool_call_id] = len(tool_seq_by_id) + 1

        tool_phase = "start"

        # 判定：終態 → 呢行係 done
        if tool_status in ("completed", "failed", "cancelled"):
            tool_phase = "done"

        tool_record: dict[str, object] = {}
        tool_record["seq"] = tool_seq_by_id.get(tool_call_id, 0)
        tool_record["tool_call_id"] = tool_call_id
        tool_record["phase"] = tool_phase
        tool_record["tool"] = display_name
        tool_record["input_full"] = str(update_payload.get("raw_input_full", ""))
        tool_record["output_full"] = str(update_payload.get("output_text_full", ""))
        tool_record["status"] = tool_status
        tool_record["datetime"] = timer_class.get_datetime()

        tool_has_content = tool_record["input_full"] != "" or tool_record["output_full"] != "" or tool_phase == "done"

        # 判定：有內容／終態才寫（in_progress 空事件唔記）
        if tool_has_content is True:
            message_log().append_tool_call(int(stream_state["response_index"]), tool_record)

            tool_open_ids = stream_state.setdefault("tool_open_ids", {})

            # 判定：start → 記住未收尾；done → 除名
            if tool_phase == "start":
                tool_open_ids[tool_call_id] = True
            else:
                tool_open_ids.pop(tool_call_id, None)

        tool_parts: list[str] = []

        # 判定：有顯示名 → 加入
        if display_name != "":
            tool_parts.append(display_name)

        # 判定：有擴展名 → 加入
        if str(update_payload.get("extension_name", "")) != "":
            tool_parts.append(str(update_payload.get("extension_name", "")))

        # 判定：有狀態 → 加入
        if tool_status != "":
            tool_parts.append(tool_status)

        # 判定：有輸入摘要 → 加入
        if str(update_payload.get("raw_input", "")) != "":
            tool_parts.append(str(update_payload.get("raw_input", "")))

        # 判定：有輸出摘要 → 加入
        if str(update_payload.get("output_text", "")) != "":
            tool_parts.append(str(update_payload.get("output_text", "")))

        _ = message_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "ACP tool: " + " · ".join(tool_parts),
            {"tool_call_id": tool_call_id},
        )
        return

    # 用量更新（暫存——本輪完結時同完成事件一齊記）
    if update_kind == "usage":
        stream_state["usage"] = update_payload


async def _on_permission_request(tool_call: object, message_id: str) -> str:
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
        _ = message_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "Permission auto-allowed (allow all for this message)",
            {"request_id": request_id, "tool_title": tool_title},
        )
        return "allow_once"

    # 判定：本輪已拒絕全部（msg_conf 旗標）→ 直接拒絕（唔出卡）
    if _is_permission_deny_all(message_id):
        _ = message_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "Permission auto-denied (deny all for this message)",
            {"request_id": request_id, "tool_title": tool_title},
        )
        return "reject_once"

    # 上一請求殘留先清（request_id 核對係第二重保險——PHP 側亦核）
    _remove_file_if_exists(permission_decision_file)

    # 寫 pending 請求（原子——PHP 讀到嘅永遠係完整 JSON）
    deadline_ts = int(time.time()) + PERMISSION_WAIT_TIMEOUT_SEC
    permission_request: dict[str, object] = {
        "request_id":  request_id,
        "tool_title":  tool_title,
        "tool_input":  tool_input if isinstance(tool_input, (dict, list)) else str(tool_input or ""),
        "created_at":  int(time.time()),
        "deadline_ts": deadline_ts,
    }
    _write_json_atomic(permission_request_file, permission_request)
    _ = message_log().append_info_log(
        EVENT_TOOL_INVOKED,
        "Permission requested: " + tool_title,
        {"request_id": request_id, "tool_title": tool_title},
    )

    # 等決定（每 tick 查一次決定檔；逾時＝自動 deny）
    while time.time() < deadline_ts:
        # 判定：使用者已取消 → 當拒絕收（watchdog 同時會終止連線）
        if message_store().get_msg_status() == "cancelled":
            _ = message_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Permission wait cancelled by user")
            return "reject_once"

        decision = _read_permission_decision(permission_decision_file, request_id)

        # 判定：使用者允許 → 回允許
        if decision == "allow":
            _ = message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed by user", {"request_id": request_id})
            return "allow_once"

        # 判定：使用者允許全部（本輪）→ 照放行（後續請求靠 msg_conf 旗標——唔再出卡）
        if decision == "allow_all":
            _ = message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed for this message (allow all)", {"request_id": request_id})
            return "allow_once"

        # 判定：使用者拒絕全部（本輪）→ 照拒（後續請求靠 msg_conf 旗標——唔再出卡）
        if decision == "deny_all":
            _ = message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied for this message (deny all)", {"request_id": request_id})
            return "reject_once"

        # 判定：使用者拒絕 → 回拒絕
        if decision == "deny":
            _ = message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied by user", {"request_id": request_id})
            return "reject_once"

        await asyncio.sleep(paths.PERMISSION_DECISION_POLL_INTERVAL_SEC)

    # 逾時（無人決定）→ 本側代寫 deny（紀錄一體）＋回拒絕
    timeout_decision: dict[str, object] = {
        "request_id": request_id,
        "decision":   "deny",
        "source":     "timeout",
        "decided_at": int(time.time()),
    }
    _write_json_atomic(permission_decision_file, timeout_decision)
    _ = message_log().append_info_log(EVENT_TOOL_INVOKED, "Permission timed out (auto deny)", {"request_id": request_id})

    return "reject_once"


def _read_permission_decision(permission_decision_file: Path, request_id: str) -> str:
    """讀決定檔（request_id 對唔上＝上一請求殘留 → 當未決；缺／壞 → 空字串）。"""
    try:
        decision_object = cast("object", json.loads(permission_decision_file.read_text(encoding="utf-8")))
    except Exception:  # noqa: BLE001
        return ""

    # 判定：非 dict → 未決
    if not isinstance(decision_object, dict):
        return ""

    decision_data = cast("dict[str, object]", decision_object)

    # 判定：request_id 唔對（殘留）→ 未決
    if str(decision_data.get("request_id", "")) != request_id:
        return ""

    return str(decision_data.get("decision", ""))


def _is_permission_allow_all(_message_id: str) -> bool:
    """本輪已允許全部？（讀 msg_conf 旗標 permission_allow_all——每次問之前讀硬檔；缺／壞 → False）。"""
    message_dir = message_store().get_message_dir()

    permission_conf_file = message_context.get_msg_file_path(message_dir, "conf")

    try:
        conf_object = cast("object", json.loads(permission_conf_file.read_text(encoding="utf-8")))
    except Exception:  # noqa: BLE001
        return False

    # 判定：非 dict → 未允許
    if not isinstance(conf_object, dict):
        return False

    conf_data = cast("dict[str, object]", conf_object)

    return bool(conf_data.get("permission_allow_all", False))


def _is_permission_deny_all(_message_id: str) -> bool:
    """本輪已拒絕全部？（讀 msg_conf 旗標 permission_deny_all——每次問之前讀硬檔；缺／壞 → False）。"""
    message_dir = message_store().get_message_dir()

    permission_conf_file = message_context.get_msg_file_path(message_dir, "conf")

    try:
        conf_object = cast("object", json.loads(permission_conf_file.read_text(encoding="utf-8")))
    except Exception:  # noqa: BLE001
        return False

    # 判定：非 dict → 未拒絕
    if not isinstance(conf_object, dict):
        return False

    conf_data = cast("dict[str, object]", conf_object)

    return bool(conf_data.get("permission_deny_all", False))


def _write_json_atomic(target_file: Path, json_payload: dict[str, object]) -> None:
    """原子寫 JSON（tmp＋replace——讀者唔會見到半截檔）。"""
    temporary_file = target_file.with_suffix(target_file.suffix + ".tmp")
    _ = temporary_file.write_text(json.dumps(json_payload, ensure_ascii=False), encoding="utf-8")
    os.replace(temporary_file, target_file)


def _remove_file_if_exists(target_file: Path) -> None:
    """檔案存在即刪（殘留清理——唔存在＝正常）。"""
    try:
        target_file.unlink()
    except FileNotFoundError:
        pass


def _on_prompt_finished(prompt_response: object, stream_state: StreamState) -> None:
    """
    本輪結束（PromptResponse）——契約收尾：
    用量記 RESPONSE_COMPLETED（成功事件）；stop_reason＝cancelled 除外（取消路徑唔當完成）。
    """
    stop_reason = str(getattr(prompt_response, "stop_reason", "") or "")

    # 判定：協定報 cancelled（本側曾發取消）→ 唔記完成（收尾交取消路徑）
    if stop_reason == "cancelled":
        _close_open_tool_calls(stream_state, "aborted")

        _ = message_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "ACP turn cancelled")
        return

    usage = stream_state["usage"]
    usage_metadata: dict[str, object] = {
        "total_tokens":  usage.get("used", 0),
        "context_size":  usage.get("size", 0),
        "cost_usd":      usage.get("cost_usd", 0),
    }
    _close_open_tool_calls(stream_state, "completed")

    _ = message_log().append_success_log(EVENT_RESPONSE_COMPLETED, "ACP stream completed", usage_metadata)
    _ = message_status().apply(EVENT_RESPONSE_COMPLETED)


def _on_cancelled() -> None:
    """使用者取消（watchdog 測到）——記事件一次（終止緊隨其後）。"""
    _ = message_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — stopping goose (ACP)")


def _close_open_tool_calls(stream_state: dict, status: str) -> None:
    """未收尾嘅工具調用補 done 行（goose 對快命令唔發 completed——收尾時自行埋單）。"""
    open_ids = stream_state.setdefault("tool_open_ids", {})
    seq_by_id = stream_state.setdefault("tool_seq_by_id", {})

    # 逐個未收尾嘅 id：補 done
    for tool_call_id in list(open_ids.keys()):
        tool_record: dict[str, object] = {}
        tool_record["seq"] = seq_by_id.get(tool_call_id, 0)
        tool_record["tool_call_id"] = tool_call_id
        tool_record["phase"] = "done"
        tool_record["tool"] = ""
        tool_record["input_full"] = ""
        tool_record["output_full"] = ""
        tool_record["status"] = status
        tool_record["datetime"] = timer_class.get_datetime()

        message_log().append_tool_call(int(stream_state["response_index"]), tool_record)

        open_ids.pop(tool_call_id, None)


def _append_job_stdout(job_stdout_file: Path, raw_line: str) -> None:
    """raw 行 append 寫入 job_stdout（原樣——首寫即建立檔）。"""
    with job_stdout_file.open("a", encoding="utf-8", errors="replace") as output_file:
        _ = output_file.write(raw_line + "\n")

#!/usr/bin/env python3
"""
openhands_api_entry.py

OpenHands agent-server（B2 原生 API／WS）執行入口（openhands_api_entry_class singleton）。

兩種用法：
    冷路徑 run_coder（每訊息一子程序——spawn target）
    暖路徑 preload＋run_job（常駐 worker——每工單呼叫 run_job）

流程：語境恢復 → 金鑰重讀 → 容器確保 → conversation 續／建 → WS 連線 → 送訊息 →
      事件翻譯（StreamingDeltaEvent → thinking／result 段檔；ActionEvent／ObservationEvent → TOOL_INVOKED；
      execution_status → FSM）→ 權限卡（waiting_for_confirmation → 檔案契約等決定）→ 收尾。

輸出契約同其他引擎一致：response 段檔／msg_stdout 留痕／FSM 事件／權限卡檔案。
"""

import hashlib
import json
import os
import time
from pathlib import Path

from ..context import *
from ..msg.msg_events import *
from ..runtime import sc_logger

from .auth_setup import api_key_loader_class
from .openhands_api_client import openhands_api_client_class


# 權限請求等待上限（秒——無人決定即自動拒）
PERMISSION_WAIT_TIMEOUT_SEC = 60

# 權限決定輪詢間隔（秒）
PERMISSION_DECISION_POLL_INTERVAL_SEC = 0.4

# provider 前綴 → API base（OpenHands LLM config；key 由 .env 同名 *_API_KEY 提供）
PROVIDER_BASE_URL_TABLE = {
    "deepseek":   "https://api.deepseek.com",
    "openrouter": "https://openrouter.ai/api/v1",
    "openai":     "https://api.openai.com/v1",
    "kilo":       "https://api.kilo.ai/api/gateway/v1",
}

# provider 前綴 → .env 內 API key 名
PROVIDER_API_KEY_NAME_TABLE = {
    "deepseek":   "DEEPSEEK_API_KEY",
    "openrouter": "OPENROUTER_API_KEY",
    "openai":     "OPENAI_API_KEY",
    "kilo":       "KILO_API_KEY",
}

# agent 工具清單（lowercase——agent-server /api/tools/ 登記名）
AGENT_TOOL_NAMES = ["terminal", "file_editor"]

# 官方 confirmation policy 值（manifest chat_mode[].mode 直接存呢三個值之一）
OFFICIAL_POLICY_KINDS = ("AlwaysConfirm", "ConfirmRisky", "NeverConfirm")

# 舊 chat 模式值（goose／aider 時代）→ 官方 policy（切引擎後舊 chat 語義唔走樣）
LEGACY_CHAT_MODE_TABLE = {
    "ask":   "AlwaysConfirm",
    "plan":  "ConfirmRisky",
    "build": "NeverConfirm",
}


class openhands_api_entry_class:
    """執行入口（singleton——整個程序只有一個實例）。"""

    _instance: "openhands_api_entry_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            cls._instance._last_action_title = ""
            cls._instance._last_action_input = None
        return cls._instance

    @classmethod
    def get_instance(cls) -> "openhands_api_entry_class":
        """取得 singleton。"""
        return cls()

    def preload(self) -> None:
        """預載檢查（暖 worker 開機一次）：只驗 .env 設定齊唔齊（實際錯誤留待工單回報）。"""
        client = openhands_api_client_class.get_instance()

        try:
            client.configure(self._resolve_env_file_path())
            sc_logger.log("preload: agent-server = " + client.base_url)
        except Exception as preload_error:  # noqa: BLE001
            sc_logger.log("preload: openhands api config error: " + str(preload_error)[:200])

    def run_coder(self, daemon_conf_path: str, msg_conf_path: str, state_channel) -> None:
        """冷路徑子程序入口（spawn target）：重初始化 → 綁通道 → 執行單次工單。"""
        daemon().load_daemon_conf_data(Path(daemon_conf_path), is_reload=True)
        msg_state_machine().attach_channel(state_channel)
        self.run_job(msg_conf_path)

    # ----------------------------------------------------------
    # 主流程
    # ----------------------------------------------------------
    def run_job(self, msg_conf_path: str) -> None:
        """單次查詢：容器 → conversation → WS 串流 → 收尾。"""
        msg_main().load_msg_conf_data(Path(msg_conf_path))

        daemon_conf_data = daemon().get_daemon_conf_data()
        oauth_keys_path = str(daemon_conf_data.get("daemon_oauth_keys_path", "") or "")

        # 判定：有 .env 路徑 → 金鑰即時重讀
        if oauth_keys_path != "":
            api_key_loader_class.get_instance().reload(Path(oauth_keys_path))

        chat_id = msg_main().get_chat_id()
        message_id = msg_main().get_message_id()
        content = msg_main().get_msg_prompt()
        msg_conf_data = msg_main().get_msg_conf_data()
        project_path = str(msg_conf_data.get("project_path", ""))
        model_name = str(msg_conf_data.get("model_name", ""))
        request_timeout = int(msg_conf_data.get("request_timeout", 600) or 600)
        msg_stdout_file = msg_main().get_msg_file_path("stdout", message_id)

        client = openhands_api_client_class.get_instance()
        conversation_id = ""
        self._permission_denied = False

        try:
            client.configure(oauth_keys_path)

            # 判定：專案路徑缺失／唔存在 → 失敗
            if project_path == "" or not Path(project_path).is_dir():
                raise RuntimeError("project path missing: " + project_path)

            llm_config = self._build_llm_config(model_name)
            working_dir_container = client.to_container_path(project_path)
            confirmation_policy = self._resolve_confirmation_policy(msg_conf_data)
            security_analyzer = self._resolve_security_analyzer(confirmation_policy)

            client.ensure_container_running()

            # conversation 續／建（每 chat 一條——存 localdb/openhands_conversations/<chat_id>.json）
            conversation_id = self._resolve_conversation(
                client,
                chat_id,
                working_dir_container,
                llm_config,
                confirmation_policy,
                model_name,
                security_analyzer,
            )

            # 清理上一輪殘留嘅等待確認（避免卡住成條 conversation）
            conversation_before = client.get_conversation(conversation_id) or {}
            if str(conversation_before.get("execution_status", "")) == "waiting_for_confirmation":
                client.respond_confirmation(conversation_id, False, "Cleared stale confirmation")
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Cleared stale confirmation before new turn")

            # 每輪同步 policy＋analyzer（chat_mode 可能中途改；同一個 conversation 即時生效）
            client.set_confirmation_policy(conversation_id, confirmation_policy)
            client.set_security_analyzer(conversation_id, security_analyzer)

            # 環境說明（等價 goose .goosehints）——首次／有變時併入本條 user 訊息
            prompt_text, environment_hash = self._compose_prompt_with_environment(chat_id, str(msg_conf_data.get("project_slug", "")), content)

            self._append_stdout(msg_stdout_file, "conversation_id=" + conversation_id)
            self._append_stdout(msg_stdout_file, "working_dir=" + working_dir_container)

            msg_log().append_info_log(EVENT_SUBPROCESS_READY, "agent-server conversation ready: " + conversation_id)
            msg_state_machine().apply_and_report(EVENT_SUBPROCESS_READY)

            deadline_ts = time.time() + request_timeout
            stream_state = {
                "content_seen":        False,
                "thinking_seen":       False,
                "last_result_text":    "",
                "last_thinking_text":  "",
                "finish_status":       "",
                "failure_message":     "",
                "running_seen":        False,
            }

            # WS 先連（resend_mode=all 補回舊事件）→ 連線成功即送訊息（零競態）
            def on_event(event_payload: dict) -> str:
                """事件翻譯（回 "stop" ＝收尾）。"""
                return self._handle_event(client, conversation_id, event_payload, stream_state, msg_stdout_file)

            stream_result = client.stream_events(
                conversation_id,
                on_event,
                lambda: msg_main().get_msg_status() == "cancelled",
                deadline_ts,
                on_open=lambda: self._send_prompt(client, conversation_id, chat_id, prompt_text, environment_hash),
                skip_until_prompt_text=prompt_text,
            )

            # 判定：逾時 → 中斷＋失敗
            if stream_result == "timeout":
                client.interrupt(conversation_id)
                msg_log().append_error_log(EVENT_FAILURE_RECORDED, "agent-server timeout (" + str(request_timeout) + "s)")
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
                return

            # 判定：使用者取消 → 中斷＋取消
            if stream_result == "cancelled":
                client.interrupt(conversation_id)
                msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — interrupted agent-server")
                msg_state_machine().apply_and_report(EVENT_CANCELLATION_REQUESTED)
                return

            # 判定：連線斷（非正常結束）→ 失敗
            if stream_result == "closed":
                msg_log().append_error_log(EVENT_FAILURE_RECORDED, "agent-server websocket closed unexpectedly")
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
                return

            # 判定：server 報錯狀態 → 失敗
            if stream_state["finish_status"] in ("error", "failed"):
                msg_log().append_error_log(EVENT_FAILURE_RECORDED, "agent-server execution failed: " + stream_state["failure_message"])
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
                return

            # 有權限被拒而 agent 冇再回文字 → 補一句說明（拒絕屬正常結束，唔算失敗）
            if stream_state["last_result_text"] == "" and self._permission_denied:
                msg_log().append_response_result(0, "（已拒絕工具執行，本輪結束）")
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "turn ended after permission denial (no agent text)")

            usage_metadata = self._build_usage_metadata(client, conversation_id)
            msg_log().append_success_log(EVENT_RESPONSE_COMPLETED, "agent-server stream completed", usage_metadata)
            msg_state_machine().apply_and_report(EVENT_RESPONSE_COMPLETED)

        # 盲捕例外（單次 run 必須完整收尾）
        except Exception as exc:  # noqa: BLE001
            msg_log().append_error_log(
                EVENT_FAILURE_RECORDED,
                type(exc).__name__,
                {"exception_type": type(exc).__name__, "exception_message": str(exc)},
                detail=str(exc),
            )
            sc_logger.log("error chat_id=" + str(chat_id) + ": " + str(exc))
            msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)

    # ----------------------------------------------------------
    # 事件翻譯
    # ----------------------------------------------------------
    def _handle_event(self, client, conversation_id: str, event_payload: dict, stream_state: dict, msg_stdout_file: Path) -> str:
        """單一事件 → 契約輸出；回 "stop" 即結束串流。"""
        event_kind = str(event_payload.get("kind", ""))
        self._append_stdout(msg_stdout_file, json.dumps(event_payload, ensure_ascii=False)[:2000])

        # 串流文字增量（content → result；reasoning_content → thinking）
        if event_kind == "StreamingDeltaEvent":
            return self._handle_streaming_delta(event_payload, stream_state)

        # 代理最終訊息（source=agent；冇 delta 時補回全文）
        if event_kind == "MessageEvent":
            self._handle_message_event(event_payload, stream_state)
            return ""

        # 動作（工具呼叫——記 TOOL_INVOKED；FinishAction＝最終回覆）
        if event_kind == "ActionEvent":
            self._handle_action_event(event_payload, stream_state)
            return ""

        # 觀察（工具結果——記 trace）
        if event_kind == "ObservationEvent":
            self._handle_observation_event(event_payload)
            return ""

        # 狀態更新（execution_status：running／waiting_for_confirmation／finished／error）
        if event_kind == "ConversationStateUpdateEvent":
            return self._handle_state_update(client, conversation_id, event_payload, stream_state)

        return ""

    def _handle_streaming_delta(self, event_payload: dict, stream_state: dict) -> str:
        """StreamingDeltaEvent → thinking／result 段檔（首個內容＝連通）。"""
        reasoning_text = str(event_payload.get("reasoning_content", "") or "")
        content_text = str(event_payload.get("content", "") or "")

        # 判定：無增量內容 → 免記
        if reasoning_text == "" and content_text == "":
            return ""

        # 判定：首次見內容 → 連通（一次）
        if stream_state["content_seen"] is False:
            stream_state["content_seen"] = True
            msg_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "agent output arrived")
            msg_state_machine().apply_and_report(EVENT_CONNECTION_ESTABLISHED)

        # 思考增量
        if reasoning_text != "":
            msg_log().append_response_thinking(0, reasoning_text)
            stream_state["last_thinking_text"] += reasoning_text

        # 答案增量（首段記串流開啟）
        if content_text != "":
            msg_log().append_response_result(0, content_text)
            stream_state["last_result_text"] += content_text
        return ""

    def _handle_message_event(self, event_payload: dict, stream_state: dict) -> None:
        """代理訊息（無 delta 時補全文；有 delta 已寫＝唔重複）。"""
        source_name = str(event_payload.get("source", ""))

        # 判定：非代理訊息 → 免記
        if source_name != "agent":
            return

        llm_message = event_payload.get("llm_message", {}) or {}
        content_parts = llm_message.get("content", []) or []
        full_text = ""

        # 逐段抽文字
        for content_part in content_parts:
            # 判定：文字段 → 併入
            if isinstance(content_part, dict) and content_part.get("type") == "text":
                full_text += str(content_part.get("text", ""))

        # 判定：空文字 → 免記
        if full_text.strip() == "":
            return

        # 判定：本輪已有串流文字 → 最終訊息唔重複寫
        if stream_state["last_result_text"] != "":
            return

        # 判定：首次見內容 → 連通
        if stream_state["content_seen"] is False:
            stream_state["content_seen"] = True
            msg_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "agent output arrived")
            msg_state_machine().apply_and_report(EVENT_CONNECTION_ESTABLISHED)

        msg_log().append_response_result(0, full_text)
        stream_state["last_result_text"] += full_text

    def _handle_action_event(self, event_payload: dict, stream_state: dict) -> None:
        """ActionEvent → TOOL_INVOKED；FinishAction 嘅 message＝最終回覆（寫 result）。"""
        action_payload = event_payload.get("action", {}) or {}
        action_kind = str(action_payload.get("kind", "action"))
        action_title = str(action_payload.get("command", "") or action_payload.get("message", "") or action_kind)

        # 判定：FinishAction → 最終回覆文字寫入 result（唔當工具活動）
        if action_kind == "FinishAction":
            finish_text = str(action_payload.get("message", "") or "")

            # 判定：有文字 → 記連通＋寫 result
            if finish_text.strip() != "":
                if stream_state["content_seen"] is False:
                    stream_state["content_seen"] = True
                    msg_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "agent output arrived")
                    msg_state_machine().apply_and_report(EVENT_CONNECTION_ESTABLISHED)
                msg_log().append_response_result(0, finish_text)
                stream_state["last_result_text"] += finish_text
            return

        self._last_action_title = action_title
        self._last_action_input = action_payload

        msg_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "tool: " + action_kind + " — " + action_title[:200],
            {"tool_call_id": str(event_payload.get("id", ""))},
        )

    def _handle_observation_event(self, event_payload: dict) -> None:
        """ObservationEvent → trace（輸出截短）。"""
        observation_payload = event_payload.get("observation", {}) or {}
        content_parts = observation_payload.get("content", []) or []
        observation_text = ""

        # 逐段抽文字
        for content_part in content_parts:
            # 判定：文字段 → 併入
            if isinstance(content_part, dict) and content_part.get("type") == "text":
                observation_text += str(content_part.get("text", ""))

        msg_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "tool result: " + observation_text[:200].replace("\n", " "),
            {"is_error": bool(observation_payload.get("is_error", False))},
        )

    def _handle_state_update(self, client, conversation_id: str, event_payload: dict, stream_state: dict) -> str:
        """ConversationStateUpdateEvent → FSM／權限卡／結束判定。"""
        state_key = str(event_payload.get("key", ""))
        state_value = event_payload.get("value", "")

        # 只處理執行狀態
        if state_key != "execution_status":
            return ""

        execution_status = str(state_value).strip().strip('"')

        # 判定：等權限 → 出卡／等決定／回應
        if execution_status == "waiting_for_confirmation":
            self._handle_confirmation(client, conversation_id)
            return ""

        # 判定：開始執行 → 記住（之後 idle 才算本輪完）
        if execution_status == "running":
            stream_state["running_seen"] = True
            return ""

        # 判定：完成 → 停
        if execution_status == "finished":
            stream_state["finish_status"] = "finished"
            return "stop"

        # 判定：idle 但未見過 running／內容 → 之前狀態殘留，唔當完
        if execution_status == "idle":
            if stream_state["running_seen"] is False and stream_state["content_seen"] is False:
                return ""
            stream_state["finish_status"] = "finished"
            return "stop"

        # 判定：錯誤 → 記住並停
        if execution_status in ("error", "failed"):
            stream_state["finish_status"] = "error"
            stream_state["failure_message"] = str(state_value)[:200]
            return "stop"

        return ""

    # ----------------------------------------------------------
    # 權限卡
    # ----------------------------------------------------------
    def _handle_confirmation(self, client, conversation_id: str) -> None:
        """權限請求：旗標／檔案契約 → accept 回應。"""
        message_id = msg_main().get_message_id()
        permission_request_file = msg_main().get_msg_file_path("permission", message_id)
        permission_decision_file = msg_main().get_msg_file_path("permission_decision", message_id)
        request_id = str(int(time.time() * 1000))

        # 判定：本輪已允許全部 → 直接放行
        if self._read_conf_flag(message_id, "permission_allow_all"):
            self._respond_if_waiting(client, conversation_id, True)
            msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission auto-allowed (allow all for this message)")
            return

        # 判定：本輪已拒絕全部 → 直接拒絕
        if self._read_conf_flag(message_id, "permission_deny_all"):
            self._permission_denied = True
            self._respond_if_waiting(client, conversation_id, False, "Denied for this message")
            msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission auto-denied (deny all for this message)")
            return

        # 上一請求殘留先清
        self._remove_file_if_exists(permission_decision_file)

        deadline_ts = int(time.time()) + PERMISSION_WAIT_TIMEOUT_SEC
        permission_request = {
            "request_id": request_id,
            "tool_title": self._last_action_title,
            "tool_input": self._last_action_input if isinstance(self._last_action_input, (dict, list)) else str(self._last_action_input or ""),
            "created_at": int(time.time()),
            "deadline_ts": deadline_ts,
        }
        self._write_json_atomic(permission_request_file, permission_request)
        msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission requested: " + self._last_action_title[:200], {"request_id": request_id})

        # 等決定（每 tick 查決定檔；逾時＝拒）
        while time.time() < deadline_ts:
            # 判定：使用者取消 → 當拒絕
            if msg_main().get_msg_status() == "cancelled":
                return

            decision = self._read_permission_decision(permission_decision_file, request_id)

            # 判定：允許 → 放行
            if decision in ("allow", "allow_all"):
                self._respond_if_waiting(client, conversation_id, True)
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed by user", {"request_id": request_id})
                return

            # 判定：拒絕 → 拒絕
            if decision in ("deny", "deny_all"):
                self._permission_denied = True
                self._respond_if_waiting(client, conversation_id, False, "User rejected the action.")
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied by user", {"request_id": request_id})
                return

            time.sleep(PERMISSION_DECISION_POLL_INTERVAL_SEC)

        # 逾時（無人決定）→ 自動拒
        self._permission_denied = True
        self._write_json_atomic(permission_decision_file, {"request_id": request_id, "decision": "deny", "source": "timeout", "decided_at": int(time.time())})
        self._respond_if_waiting(client, conversation_id, False, "Confirmation timed out.")
        msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission timed out (auto deny)", {"request_id": request_id})

    # ----------------------------------------------------------
    # conversation 續／建
    # ----------------------------------------------------------
    def _resolve_conversation(self, client, chat_id, working_dir_container: str, llm_config: dict, confirmation_policy: dict, model_name: str, security_analyzer: dict | None) -> str:
        """每 chat 一條 conversation（存檔；存在且工作目錄相同＝續，否則新建）。"""
        conversation_file = self._conversation_file_path(chat_id)
        saved_conversation_id = ""

        # 讀存檔（缺／壞＝空）
        try:
            saved_data = json.loads(conversation_file.read_text(encoding="utf-8"))
            saved_conversation_id = str(saved_data.get("conversation_id", ""))
        except Exception:  # noqa: BLE001
            saved_conversation_id = ""

        # 判定：有存檔 id → 驗 server 上仍存在且工作目錄相同
        if saved_conversation_id != "":
            existing_conversation = client.get_conversation(saved_conversation_id)

            # 判定：仍存在且目錄相同 → 續用
            if existing_conversation is not None:
                existing_workspace = existing_conversation.get("workspace", {}) or {}
                existing_status = str(existing_conversation.get("execution_status", ""))

                # 判定：目錄一致且狀態正常 → 回存檔 id（error 狀態＝歷史已壞，必須重建）
                if str(existing_workspace.get("working_dir", "")) == working_dir_container and existing_status != "error":
                    return saved_conversation_id

        created_conversation = client.create_conversation(working_dir_container, llm_config, AGENT_TOOL_NAMES, confirmation_policy, security_analyzer)
        new_conversation_id = str(created_conversation.get("id", ""))

        # 判定：server 未回 id → 掟錯
        if new_conversation_id == "":
            raise RuntimeError("create conversation returned no id")

        conversation_file.parent.mkdir(parents=True, exist_ok=True)
        self._write_json_atomic(conversation_file, {
            "conversation_id": new_conversation_id,
            "working_dir":     working_dir_container,
            "model_name":      model_name,
            "updated_at":      int(time.time()),
        })
        return new_conversation_id

    def _conversation_file_path(self, chat_id) -> Path:
        """conversation 存檔路徑：<storage>/localdb/openhands_conversations/<chat_id>.json。"""
        daemon_conf_data = daemon().get_daemon_conf_data()
        messages_dir = Path(str(daemon_conf_data.get("daemon_messages_dir", "")))

        # 找 "messages" 目錄（可能係 data/messages 或 data/messages/polling）——storage 根係佢上層
        storage_root = messages_dir
        while storage_root.name not in ("messages", "") and storage_root.parent != storage_root:
            storage_root = storage_root.parent
        storage_root = storage_root.parent

        return storage_root / "localdb" / "openhands_conversations" / (str(chat_id) + ".json")

    # ----------------------------------------------------------
    # 環境說明注入（goose .goosehints 等價）
    # ----------------------------------------------------------
    def _build_environment_context(self, project_slug: str) -> str:
        """三份環境檔合併（system_prompt＋system_env＋專案 env；缺檔＝略過，全缺＝空字串）。"""
        storage_dir = Path(str(daemon().get_daemon_conf_data().get("storage_dir", "")))
        source_files = [
            storage_dir / "instructions" / "system_prompt.md",
            storage_dir / "instructions" / "system_env.md",
            storage_dir / "environment" / ("env." + project_slug + ".md"),
        ]
        sections = []

        # 逐份讀（存在才加）
        for source_file in source_files:
            # 判定：檔唔存在 → 跳過
            if source_file.is_file() is False:
                continue
            sections.append(source_file.read_text(encoding="utf-8", errors="replace").strip())

        return "\n\n".join(sections)

    def _compose_prompt_with_environment(self, chat_id, project_slug: str, content: str) -> tuple:
        """環境說明首次／有變 → 併入本條 user 訊息；回 (prompt_text, hash_to_save or "")。"""
        environment_text = self._build_environment_context(project_slug)

        # 判定：三份都冇 → 原樣送出
        if environment_text == "":
            return content, ""

        environment_hash = hashlib.sha256(environment_text.encode("utf-8")).hexdigest()
        saved_hash = ""

        # 讀現存 hash（缺／壞＝空）
        try:
            saved_data = json.loads(self._conversation_file_path(chat_id).read_text(encoding="utf-8"))
            saved_hash = str(saved_data.get("env_hash", ""))
        except Exception:  # noqa: BLE001
            saved_hash = ""

        # 判定：已注入過同一版本 → 原樣送出
        if saved_hash == environment_hash:
            return content, ""

        prompt_text = environment_text + "\n\n---\n\n" + content
        msg_log().append_info_log(EVENT_SUBPROCESS_READY, "environment context attached (" + str(len(environment_text)) + " chars)")
        return prompt_text, environment_hash

    def _send_prompt(self, client, conversation_id: str, chat_id, prompt_text: str, environment_hash: str) -> None:
        """送出訊息；成功後記環境 hash（失敗＝下輪再試）。"""
        client.send_message(conversation_id, prompt_text)

        # 判定：有 hash → 記入 conversation 檔
        if environment_hash != "":
            self._update_conversation_file(chat_id, {"env_hash": environment_hash})

    def _update_conversation_file(self, chat_id, updates: dict) -> None:
        """更新 conversation 存檔欄位（缺檔＝以 updates 建立）。"""
        conversation_file = self._conversation_file_path(chat_id)
        existing_data = {}

        try:
            existing_data = json.loads(conversation_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            existing_data = {}

        existing_data.update(updates)
        conversation_file.parent.mkdir(parents=True, exist_ok=True)
        self._write_json_atomic(conversation_file, existing_data)

    # ----------------------------------------------------------
    # LLM 設定
    # ----------------------------------------------------------
    def _build_llm_config(self, model_name: str) -> dict:
        """model_name（provider/model）→ agent-server LLM config（key 由 .env 即時讀）。"""
        provider_prefix, separator, model_part = model_name.partition("/")

        # 判定：格式唔對／provider 唔識 → 掟錯
        if separator == "" or provider_prefix not in PROVIDER_BASE_URL_TABLE:
            raise RuntimeError("unsupported model for openhands api: " + model_name)

        api_key_name = PROVIDER_API_KEY_NAME_TABLE[provider_prefix]
        api_key_value = os.environ.get(api_key_name, "")

        # 判定：key 未載入 → 掟錯（顯示 key 名）
        if api_key_value == "":
            raise RuntimeError(api_key_name + " missing (oauth keys file)")

        llm_config = {
            "model":     model_name,
            "api_key":   api_key_value,
            "base_url":  PROVIDER_BASE_URL_TABLE[provider_prefix],
            "stream":    True,
            "usage_id":  "agent",
        }

        # 判定：有 reasoning effort → 帶上
        reasoning_effort = str(msg_main().get_msg_conf_data().get("reasoning_effort", "") or "")
        if reasoning_effort != "":
            llm_config["reasoning_effort"] = reasoning_effort

        return llm_config

    def _resolve_security_analyzer(self, confirmation_policy: dict) -> dict | None:
        """policy → security analyzer（smart／ConfirmRisky 用 PatternSecurityAnalyzer；其餘 None）。"""
        # 判定：ConfirmRisky → 掛 analyzer（否則 smart 等於 auto）
        if confirmation_policy.get("kind") == "ConfirmRisky":
            return {"kind": "PatternSecurityAnalyzer"}
        return None

    def _resolve_confirmation_policy(self, msg_conf_data: dict) -> dict:
        """chat_mode（HKAI key）→ manifest 對應嘅官方 policy（缺／唔識＝NeverConfirm）。"""
        chat_mode = str(msg_conf_data.get("chat_mode", "auto") or "auto")
        chat_mode_map = daemon().get_daemon_conf_data().get("daemon_chat_mode_map", {}) or {}
        policy_kind = str(chat_mode_map.get(chat_mode, ""))

        # 判定：manifest 冇 → 查舊值兼容表（ask／plan／build）
        if policy_kind not in OFFICIAL_POLICY_KINDS:
            policy_kind = LEGACY_CHAT_MODE_TABLE.get(chat_mode, "")

        # 判定：仍然唔屬官方三值 → 用 NeverConfirm
        if policy_kind not in OFFICIAL_POLICY_KINDS:
            policy_kind = "NeverConfirm"

        return {"kind": policy_kind}

    # ----------------------------------------------------------
    # 收尾／工具
    # ----------------------------------------------------------
    def _build_usage_metadata(self, client, conversation_id: str) -> dict:
        """stats → tokens／cost metadata（缺＝零）。"""
        stats_data = client.get_usage_metrics(conversation_id)
        usage_metrics = stats_data.get("usage_to_metrics", {}) or {}
        agent_metrics = usage_metrics.get("agent", {}) or {}
        token_usage = agent_metrics.get("accumulated_token_usage", {}) or {}

        prompt_tokens = int(token_usage.get("prompt_tokens", 0) or 0)
        completion_tokens = int(token_usage.get("completion_tokens", 0) or 0)

        return {
            "total_tokens":  prompt_tokens + completion_tokens,
            "context_size":  int(token_usage.get("context_window", 0) or 0),
            "cost_usd":      float(agent_metrics.get("accumulated_cost", 0) or 0),
        }

    def _respond_if_waiting(self, client, conversation_id: str, accept: bool, reason: str = "") -> None:
        """只在 server 仍 waiting_for_confirmation 時回應（本輪已收工＝唔回應，免污染歷史）。"""
        conversation_data = client.get_conversation(conversation_id) or {}
        execution_status = str(conversation_data.get("execution_status", ""))

        # 判定：已唔係等權限 → 記 log 後略過
        if execution_status != "waiting_for_confirmation":
            msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission response skipped (conversation status: " + execution_status + ")")
            return

        client.respond_confirmation(conversation_id, accept, reason)

    def _read_conf_flag(self, message_id, flag_name: str) -> bool:
        """讀 msg_conf 旗標（缺／壞＝False）。"""
        conf_file = msg_main().get_msg_file_path("conf", message_id)

        try:
            conf_data = json.loads(conf_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return False

        # 判定：非 dict → False
        if not isinstance(conf_data, dict):
            return False

        return bool(conf_data.get(flag_name, False))

    def _read_permission_decision(self, permission_decision_file: Path, request_id: str) -> str:
        """讀決定檔（request_id 對唔上＝殘留；缺／壞＝空）。"""
        try:
            decision_data = json.loads(permission_decision_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return ""

        # 判定：非 dict／id 唔同 → 未決
        if not isinstance(decision_data, dict) or str(decision_data.get("request_id", "")) != request_id:
            return ""

        return str(decision_data.get("decision", ""))

    def _write_json_atomic(self, target_file: Path, json_payload: dict) -> None:
        """原子寫 JSON（tmp＋replace）。"""
        temporary_file = target_file.with_suffix(target_file.suffix + ".tmp")
        temporary_file.write_text(json.dumps(json_payload, ensure_ascii=False), encoding="utf-8")
        os.replace(temporary_file, target_file)

    def _remove_file_if_exists(self, target_file: Path) -> None:
        """存在即刪（殘留清理）。"""
        try:
            target_file.unlink()
        except FileNotFoundError:
            pass

    def _append_stdout(self, msg_stdout_file: Path, raw_line: str) -> None:
        """raw 行 append 寫 msg_stdout。"""
        with msg_stdout_file.open("a", encoding="utf-8", errors="replace") as output_file:
            output_file.write(raw_line + "\n")

    def _resolve_env_file_path(self) -> str:
        """.env 路徑（daemon conf——缺＝空）。"""
        daemon_conf_data = daemon().get_daemon_conf_data()
        return str(daemon_conf_data.get("daemon_oauth_keys_path", "") or "")

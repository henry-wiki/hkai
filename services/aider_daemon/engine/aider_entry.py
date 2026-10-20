#!/usr/bin/env python3
"""
aider_entry.py

aider 執行入口（aider_entry_class singleton——get_instance() 取得）。

兩種用法：
    冷路徑 run_coder（每訊息一子程序——spawn target）
    暖路徑 preload＋run_job（常駐 worker——開機預載一次，每工單呼叫 run_job）

流程：語境恢復 → 環境組裝（provider／model 由 model_name 解析；edit format 由 chat_mode 對映）→
      spawn `python -m aider_daemon.acp.main`（ACP over stdio——engine/acp_client.py 驅動；權限請求經檔案契約互動）→ 收尾。

session 無續話（v0）：對話歷史由本側隨 session/new 帶入（單一 ledger——我哋 DB 永遠係真相，
唔靠引擎自身 session 持久化）。

與 goose_daemon 的引擎路對位——輸出契約（response 段檔／msg_stdout／FSM 事件）一致；
差別：aider 冇官方通道——本 daemon 專用 aider_daemon.acp 插頭（獨立 ACP server，協定同源）。
"""

import asyncio
import json
import os
import sys
import time
from pathlib import Path

from ..acp.litellm_providers import gateway_map
from ..context import *
from ..msg.msg_events import *
from ..runtime import sc_logger


# 權限請求等待上限（秒——無人決定即自動拒；本側代碼常數——暫唔 expose 喺 hkai_config.json）
PERMISSION_WAIT_TIMEOUT_SEC = 60

# 權限決定輪詢間隔（秒——ACP 權限等待每 tick 檢查一次決定檔）
PERMISSION_DECISION_POLL_INTERVAL_SEC = 0.4

# 讀類確認前綴（零寫入——如「Add file to the chat?」加檔入對話；直接放行唔出卡）
READ_ONLY_PERMISSION_PREFIXES = ("Add file to the chat?",)

# chat_mode 對映值 → aider edit format（""＝模型預設接手）
AIDER_EDIT_FORMAT_MAP = {
    "ask":       "ask",
    "architect": "architect",
    "code":      "",
}


class aider_entry_class:
    """
    執行入口（singleton——整個程序只有一個實例）。

    職責單一：建立 aider_daemon.acp 執行環境（模型環境＋解釋器＋對話歷史）並執行單次查詢；不含重試／狀態機。
    """

    _instance: "aider_entry_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "aider_entry_class":
        """取得 aider_entry_class 單例。"""
        return cls()

    def preload(self) -> None:
        """
        預載檢查（暖 worker 開機一次）：aider_daemon.acp 為本側小程式（每工單一子程序）——
        無重型引擎可預載；僅記解釋器路徑（實際錯誤留待各工單回報）。
        """
        python_executable = self._resolve_python_executable()
        sc_logger.log("preload: python executable = " + python_executable)

    def run_coder(self, daemon_conf_path: str, msg_conf_path: str, state_channel) -> None:
        """
        冷路徑子程序入口（spawn target——每訊息一程序）：重初始化 → 綁通道 → 執行單次工單。
        暖模式下不走此入口（暖 worker 直接呼叫 run_job）。
        """
        # 讀 runtime.json（is_reload=True：跳過 PID + keys，只讀 config + cwd）
        daemon().load_daemon_conf_data(Path(daemon_conf_path), is_reload=True)

        # 綁定狀態事件通道（FSM 為唯一狀態管理者；子程序自行套用並回報）
        msg_state_machine().attach_channel(state_channel)

        # 執行單次工單（與暖模式同一核心——run_job）
        self.run_job(msg_conf_path)

    def run_job(self, msg_conf_path: str) -> None:
        """
        單次查詢（暖 worker 每張工單一次）：組環境 → spawn aider_daemon.acp → 收尾。
        與冷路徑同一流程——差別只在「有無跨程序通道」。
        """
        # 讀 msg_conf.json（每張工單重新載入——語境切換）
        msg_main().load_msg_conf_data(Path(msg_conf_path))

        # 取得語境（從載入後的 msg_main 狀態）
        chat_id    = msg_main().get_chat_id()
        message_id = msg_main().get_message_id()
        content    = msg_main().get_msg_prompt()

        # msg_conf 隨身資料（cwd／模型——PHP 寫入）
        msg_conf_data = msg_main().get_msg_conf_data()
        project_path  = str(msg_conf_data.get("project_path", ""))
        model_name    = str(msg_conf_data.get("model_name", ""))

        # msg_stdout 檔路徑（純紀錄——原樣輸出）
        msg_stdout_file: Path = msg_main().get_msg_file_path("stdout", message_id)

        try:
            # aider_daemon.acp 引擎路徑（本 daemon 唯一引擎——無揀路分支）
            self._run_job_acp(msg_conf_data, content, project_path, msg_stdout_file)

        # 盲捕例外（刻意——單次 run 失敗要完整收尾，不能外洩）
        except Exception as exc:  # noqa: BLE001
            # 例外訊息寫入 msg_log（ERROR 事件——短句進 msg_log、完整內容進 error_log）
            # 記錄動作自身失敗唔可以蓋過原失敗（曾：msg_log 寫入擲錯 → 整條失敗零記錄）
            try:
                msg_log().append_error_log(
                    EVENT_FAILURE_RECORDED,
                    type(exc).__name__,
                    {
                        "exception_type": type(exc).__name__,
                        "exception_message": str(exc),
                    },
                    detail=str(exc),
                )
            except Exception as log_exc:  # noqa: BLE001
                sc_logger.log("msg_log append failed: " + str(log_exc))

            sc_logger.log("error chat_id=" + str(chat_id) + ": " + str(exc))   # sc_logger.log 內部盲捕——已安全
            msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)

    # ----------------------------------------------------------
    # aider 環境／子程序
    # ----------------------------------------------------------

    def _build_aider_env(self, model_name: str) -> dict | None:
        """
        由 model_name（litellm 式 provider/model）組 aider_daemon.acp 子程序環境（回傳＝完整 env；唔支持＝None）。

        支持：openrouter/<model>（litellm 原生）＋自訂 gateway（acp/litellm_providers.gateway_map——如 kilo/<model>）；
        key＝oauth keys 檔已載入本程序環境，子程序繼承；AIDER_ACP_ENV_FILE 另指向 oauth keys 檔
        （server 側補載備援——同源唔重工）。
        """
        # provider 前綴（第一個 "/" 前）＋模型名（後——aider 認全名，含 provider 前綴）
        provider_prefix, separator, model_part = model_name.partition("/")

        # 判定：provider 唔支持（openrouter/ 以外＋唔喺 gateway 表）→ None
        if provider_prefix not in self._supported_provider_prefixes():
            return None

        # 判定：模型名缺失 → None
        if model_part == "":
            return None

        agent_env = dict(os.environ)
        agent_env["AIDER_ACP_MODEL"]    = model_name
        agent_env["AIDER_ACP_ENV_FILE"] = str(daemon().get_daemon_conf_data().get("daemon_oauth_keys_path", "") or "")

        return agent_env

    def _supported_provider_prefixes(self) -> tuple:
        """支持嘅 provider 前綴（openrouter〔litellm 原生〕＋自訂 gateway 表——單一來源）。"""
        return ("openrouter",) + tuple(gateway_map.keys())

    def _resolve_api_key_env_name(self, provider_prefix: str) -> str:
        """provider 前綴 → key 環境變數名（openrouter＝OPENROUTER_API_KEY；自訂 gateway＝表內 api_key_env）。"""
        # 判定：openrouter（原生通道——固定 key 名）
        if provider_prefix == "openrouter":
            return "OPENROUTER_API_KEY"

        # 自訂 gateway（表內條名——如 kilo → KILO_API_KEY）
        return str(gateway_map.get(provider_prefix, {}).get("api_key_env", ""))

    def _resolve_python_executable(self) -> str:
        """Python 解釋器（runtime.json external_paths.python_executable——aider_daemon.acp spawn 用；缺＝現行解釋器）。"""
        python_executable = str(daemon().get_daemon_conf_data().get("python_executable", "") or "")

        # 判定：配置有值 → 用之
        if python_executable != "":
            return python_executable

        # 退回現行解釋器（daemon 自身——同一環境）
        return sys.executable

    def _resolve_edit_format(self) -> str:
        """
        chat_mode 對映查表（本張工單 msg_conf.chat_mode → runtime.json daemon_chat_mode_map）
        → 轉 aider edit format（ask／architect／""＝模型預設）。

        缺／未對映＝空字串（模型預設接手——記一行）。
        """
        msg_conf_data   = msg_main().get_msg_conf_data()
        chat_mode       = str(msg_conf_data.get("chat_mode", ""))
        chat_mode_map   = daemon().get_daemon_conf_data().get("daemon_chat_mode_map", {})
        chat_mode_value = str(chat_mode_map.get(chat_mode, ""))

        # 判定：未對映（缺／詞彙唔夾）→ 記行（模型預設接手）
        if chat_mode_value == "":
            sc_logger.log("chat mode not mapped for aider: '" + chat_mode + "'")
            return ""

        # 判定：對映值超出已知 edit format（將來新增模）→ 記行（模型預設接手）
        if chat_mode_value not in AIDER_EDIT_FORMAT_MAP:
            sc_logger.log("chat mode value unmapped for aider edit format: '" + chat_mode_value + "'")

        return AIDER_EDIT_FORMAT_MAP.get(chat_mode_value, "")

    def _resolve_instructions_payload(self) -> dict:
        """
        系統指示材料（發問前注入——全文＋唯讀檔列）：
            system_prompt_text  instructions/system_prompt.md 全文（交 acp 側附加喺系統提示尾）
            read_only_paths     唯讀材料列（system_env〔root〕＋project env〔environment/env.{slug}.md〕
                                ＋訊息夾 AGENTS.md〔有先帶——aider 無原生 AGENTS 讀取〕）
        缺必要檔（system_prompt／system_env 或其設定）＝RuntimeError（唔准靜默——呼叫端記失敗收工）。
        """
        daemon_conf_data  = daemon().get_daemon_conf_data()
        storage_dir       = Path(str(daemon_conf_data.get("storage_dir", "")))
        instructions_conf = daemon_conf_data.get("instructions", {})

        # 系統提示全文（root 檔——永遠取來源）
        system_prompt_relative = str(instructions_conf.get("system_prompt", {}).get("path", ""))

        # 判定：設定缺路徑 → 爆（唔准靜默）
        if system_prompt_relative == "":
            raise RuntimeError("system instructions config missing: instructions.system_prompt.path")

        system_prompt_path = storage_dir / system_prompt_relative

        # 判定：檔唔喺度 → 爆（唔准靜默）
        if system_prompt_path.is_file() is False:
            raise RuntimeError("system instructions unavailable: " + str(system_prompt_path))

        # 系統環境（root 檔——同一模式）
        system_env_relative = str(instructions_conf.get("system_env", {}).get("path", ""))

        # 判定：設定缺路徑 → 爆
        if system_env_relative == "":
            raise RuntimeError("system instructions config missing: instructions.system_env.path")

        system_env_path = storage_dir / system_env_relative

        # 判定：檔唔喺度 → 爆
        if system_env_path.is_file() is False:
            raise RuntimeError("system instructions unavailable: " + str(system_env_path))

        # 唯讀材料（按序：系統環境 → 專案環境 → 訊息夾規則）
        read_only_paths = [str(system_env_path)]

        # 專案環境（後備源——偵測失敗可缺；缺＝略過記行）
        project_env_path = storage_dir / "environment" / ("env." + msg_main().get_project_slug() + ".md")

        # 判定：檔喺度 → 帶
        if project_env_path.is_file() is True:
            read_only_paths.append(str(project_env_path))
        else:
            sc_logger.log("project environment file missing: " + str(project_env_path))

        # 訊息夾 AGENTS.md（專案規則副本——無檔＝略過、無錯誤）
        message_agents_path = msg_main().get_message_dir(msg_main().get_message_id()) / "AGENTS.md"

        # 判定：檔喺度 → 帶
        if message_agents_path.is_file() is True:
            read_only_paths.append(str(message_agents_path))

        return {
            "system_prompt_text": system_prompt_path.read_text(encoding="utf-8"),
            "read_only_paths":    read_only_paths,
        }

    def _resolve_runtime_conf_path(self) -> str:
        """runtime.json 路徑（acp 子程序 litellm_hook 內載入用——未載入 daemon conf 時回空字串＝hook inert）。"""
        try:
            return str(daemon().get_daemon_conf_path())
        except Exception:  # noqa: BLE001
            return ""

    def _run_job_acp(self, msg_conf_data: dict, content: str, project_path: str, msg_stdout_file: Path) -> None:
        """
        aider_daemon.acp 引擎路徑：spawn `python -m aider_daemon.acp.main`（ACP over stdio——獨立小程序，協定同源）。
        模型／key 檢驗同 goose 引擎路同款；session 無續話（v0）——對話歷史由本側隨 session/new 帶入
        （單一 ledger：我哋 DB 永遠係真相，唔靠引擎自身 session 持久化）。
        """
        # 模型（msg_conf——litellm 式 provider/model）
        model_name = str(msg_conf_data.get("model_name", ""))

        # 環境組裝（provider／model 由 model_name 解析——唔支持＝None）
        agent_env = self._build_aider_env(model_name)

        # 判定：model_name 唔支持（openrouter/ 以外＋唔喺 gateway 表）→ 記錯誤收工
        if agent_env is None:
            msg_log().append_error_log(
                EVENT_FAILURE_RECORDED,
                "Unsupported model for aider_daemon.acp engine (openrouter/ or custom gateway only)",
                {"model_name": model_name},
            )
            msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
            return

        # provider 前綴（key 檢查用——同 _build_aider_env 同一解析）
        provider_prefix = model_name.partition("/")[0]

        # 該 provider 嘅 key 環境變數名（openrouter＝原生名；自訂 gateway＝表內條名）
        api_key_env_name = self._resolve_api_key_env_name(provider_prefix)

        # 判定：key 未載入（oauth keys 檔缺該條）→ 記錯誤收工
        if not agent_env.get(api_key_env_name, ""):
            msg_log().append_error_log(
                EVENT_FAILURE_RECORDED,
                api_key_env_name + " missing (oauth keys file)",
            )
            msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
            return

        # 判定：工作目錄缺／唔存在 → 記錯誤收工（aider 要真實專案根）
        if project_path == "" or not Path(project_path).is_dir():
            msg_log().append_error_log(
                EVENT_FAILURE_RECORDED,
                "project path missing: " + project_path,
                {"project_path": project_path},
            )
            msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
            return

        # 編輯格式（chat_mode 對映——ask／architect／code；空＝模型預設）
        agent_env["AIDER_ACP_EDIT_FORMAT"] = self._resolve_edit_format()

        # litellm 攔截器語境（aider_daemon.acp litellm_hook——stream_log／摘要檔／事件；缺值＝hook inert）
        agent_env["AIDER_ACP_MSG_CONF_PATH"] = str(msg_main().get_msg_file_path("conf", msg_main().get_message_id()))
        agent_env["AIDER_ACP_RUNTIME_PATH"] = self._resolve_runtime_conf_path()

        # Python 解釋器（runtime.json external_paths——缺＝現行解釋器）
        python_executable = self._resolve_python_executable()

        # 套件根（services/——aider_daemon.acp 隨 PYTHONPATH 落子程序：cwd 係專案根，唔係 services）
        services_dir = str(Path(__file__).resolve().parents[2])

        # 自帶 aider 套件目錄（runtime.json external_paths.aider_pylibs_dir——空白＝無；私隱值讀 .env）
        aider_pylibs_dir = str(daemon().get_daemon_conf_data().get("aider_pylibs_dir", "") or "")

        # PYTHONPATH 分段（自帶 aider → services → 原有；空段唔入）
        pythonpath_entries = []

        # 判定：有設值 → 睇目錄存在（存在＝排最前，優先過 services／site-packages）
        if aider_pylibs_dir != "":
            if Path(aider_pylibs_dir).is_dir():
                pythonpath_entries.append(aider_pylibs_dir)

        # 本套件根（aider_daemon.acp 隨 PYTHONPATH 落子程序）
        pythonpath_entries.append(services_dir)

        # 判定：原有 PYTHONPATH → 殿後（保留外層語境）
        existing_pythonpath = agent_env.get("PYTHONPATH", "")
        if existing_pythonpath != "":
            pythonpath_entries.append(existing_pythonpath)

        agent_env["PYTHONPATH"] = os.pathsep.join(pythonpath_entries)

        # 記實際生效路徑（自帶＝vendored 目錄；否則＝系統已裝）
        sc_logger.log("pythonpath: " + agent_env["PYTHONPATH"])

        # 對話 session 名（只作記錄／標籤用——aider_daemon.acp v0 無續話）
        chat_id = msg_main().get_chat_id()

        # 對話歷史（我方 DB——今次問題以外，排除 failed；每輪重建，單一 ledger）
        conversation_history = agent_history().build_agent_history(chat_id, msg_main().get_message_id())

        # 卡死時限（秒——msg_conf request_timeout；缺／0 → 180 後備；無協定活動逾時＝當卡死）
        prompt_stall_timeout_sec = int(msg_conf_data.get("request_timeout", 0) or 0)

        # 判定：冇設定 → 後備值
        if prompt_stall_timeout_sec <= 0:
            prompt_stall_timeout_sec = 180

        # 系統指示（hkai——發問前注入：system_prompt 全文＋唯讀材料；缺必要檔＝掟錯，外層記失敗）
        instructions_payload = self._resolve_instructions_payload()

        # 本次連線參數（dict——key 同名變數喺 acp_client 內解包）
        session_request = {
            "agent_command":   [python_executable, "-m", "aider_daemon.acp.main"],
            "agent_env":       agent_env,
            "project_path":    project_path,
            "session_title":   "",
            "prompt_text":     content,
            "session_mode":    "",
            "resume_session":  False,
            "session_history": conversation_history,
            "system_instructions": instructions_payload,
            "prompt_stall_timeout_sec": prompt_stall_timeout_sec,
        }

        # 交 ACP 路徑（本 daemon 專用通道——回呼／串流語義同輸出契約）
        self._run_acp_session(session_request, "aider_daemon.acp hkai-chat-" + str(chat_id), msg_stdout_file)

    # ----------------------------------------------------------
    # ACP 路徑（aider_daemon.acp——stdio 協定；協定細節喺 engine/acp_client.py）
    # ----------------------------------------------------------

    def _run_acp_session(self, session_request: dict, session_label: str, msg_stdout_file: Path) -> None:
        """
        ACP 路徑：串流狀態＋回呼組裝（輸出契約／權限卡語義）→ 交協定客戶端跑（同步阻塞——回呼驅動一切）。
        """
        message_id = msg_main().get_message_id()

        # 串流翻譯狀態（跨回呼共享）
        stream_state = {
            "response_index":       0,
            "current_message_id":   "",
            "first_content_seen":   False,
            "stream_opened_logged": False,
            "usage":                {},
        }

        # 回呼（acp_client 事件 → 本側契約——逐支見各方法）
        callbacks = {
            "on_stdio_line":         lambda stdio_line: self._append_msg_stdout(msg_stdout_file, stdio_line),
            "on_ready":              lambda session_id: self._acp_ready(session_label),
            "on_update":             lambda update_kind, update_payload: self._acp_update(update_kind, update_payload, stream_state),
            "on_permission_request": lambda tool_call: self._acp_permission_request(tool_call, message_id),
            "on_prompt_finished":    lambda prompt_response: self._acp_prompt_finished(prompt_response, stream_state),
            "on_cancelled":          lambda: self._acp_cancelled(),
            "is_cancelled":          lambda: msg_main().get_msg_status() == "cancelled",
        }

        # 交協定客戶端跑（同步阻塞——回呼驅動一切）
        acp_client().run_session(session_request, callbacks)

    def _acp_ready(self, session_label: str) -> None:
        """ACP 就緒（連線＋session＋模式已備——未查詢）——同一「就緒」事件語義。"""
        msg_log().append_info_log(EVENT_SUBPROCESS_READY, "ACP session ready: " + session_label)
        msg_state_machine().apply_and_report(EVENT_SUBPROCESS_READY)

    def _acp_update(self, update_kind: str, update_payload: dict, stream_state: dict) -> None:
        """
        ACP 事件 → 契約輸出（對位輸出契約翻譯語義）：
            thought → {i}.thinking 檔；text → {i}.result 檔（首段記串流開啟）；replay → {i}.replay 檔（load 重播——獨立歸檔，唔屬本輪回應）；
            tool → TOOL_INVOKED 事件；usage → 暫存（本輪完結時記）；other → 免記（協定追蹤已留痕）。
        段落 index：_meta.messageId 換新（上一輪已有內容）即前進——同 CLI message id 語義。
        """
        # 引擎重播段（load 重播——唔屬本輪回應；獨立歸檔；唔觸發連通／段 index 前進）
        if update_kind == "replay":
            msg_log().append_response_replay(stream_state["response_index"], update_payload.get("text", ""))
            return

        message_id = update_payload.get("message_id", "")

        # 首個內容（thought／text）→ 連通（replying——只發一次）
        is_content_update = update_kind in ("thought", "text")
        if is_content_update and stream_state["first_content_seen"] is False:
            stream_state["first_content_seen"] = True
            msg_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "aider_daemon.acp output arrived")
            msg_state_machine().apply_and_report(EVENT_CONNECTION_ESTABLISHED)

        # 判定：新 message id（上一輪已有內容）→ 段落 index 前進（每輪一對檔）
        if message_id != "" and stream_state["current_message_id"] != "" and message_id != stream_state["current_message_id"]:
            stream_state["response_index"] += 1

        # 判定：本輪 message id → 記為當前
        if message_id != "":
            stream_state["current_message_id"] = message_id

        # 思考段（reasoning——入 {i}.thinking 檔）
        if update_kind == "thought":
            msg_log().append_response_thinking(stream_state["response_index"], update_payload.get("text", ""))
            return

        # 主回答段（入 {i}.result 檔；首段記串流開啟）
        if update_kind == "text":
            # 判定：尚未記串流開啟 → 記一次（trace）
            if stream_state["stream_opened_logged"] is False:
                stream_state["stream_opened_logged"] = True
                msg_log().append_info_log(EVENT_REPLY_STREAM_OPENED, "aider_daemon.acp reply stream opened")
            msg_log().append_response_result(stream_state["response_index"], update_payload.get("text", ""))
            return

        # 工具活動（TOOL_INVOKED 事件——trace）
        if update_kind == "tool":
            msg_log().append_info_log(
                EVENT_TOOL_INVOKED,
                "ACP tool: " + update_payload.get("title", ""),
                {"tool_call_id": update_payload.get("tool_call_id", "")},
            )
            return

        # 用量更新（暫存——本輪完結時同完成事件一齊記）
        if update_kind == "usage":
            stream_state["usage"] = update_payload

    async def _acp_permission_request(self, tool_call, message_id: str) -> str:
        """
        ACP 權限請求（async——acp_client await 本支）：寫 pending 請求 → 等決定
        （上限 PERMISSION_WAIT_TIMEOUT_SEC 秒、每 PERMISSION_DECISION_POLL_INTERVAL_SEC 秒查一次）
        → 回選項 id（allow_once／reject_once）；逾時＝本側代寫 deny 決定。
        讀類請求（零寫入——加檔入對話等）＝免問：唔寫 pending、直接回 allow_once。
        本輪已「允許全部」（msg_conf 旗標）＝免問：唔寫 pending、直接回 allow_once。
        本輪已「拒絕全部」（msg_conf 旗標）＝免問：唔寫 pending、直接回 reject_once。
        """
        permission_request_file  = msg_main().get_msg_file_path("permission", message_id)
        permission_decision_file = msg_main().get_msg_file_path("permission_decision", message_id)

        request_id = str(getattr(tool_call, "tool_call_id", "") or "")
        tool_title = str(getattr(tool_call, "title", "") or "")
        tool_input = getattr(tool_call, "raw_input", None)

        # 判定：讀類請求（零寫入——加檔入對話等）→ 直接放行（唔出卡——查詢唔需要權限）
        if self._is_read_only_permission(tool_title):
            msg_log().append_info_log(
                EVENT_TOOL_INVOKED,
                "Permission auto-allowed (read-only)",
                {"request_id": request_id, "tool_title": tool_title},
            )
            return "allow_once"

        # 判定：本輪已允許全部（msg_conf 旗標）→ 直接放行（唔出卡）
        if self._is_permission_allow_all(message_id):
            msg_log().append_info_log(
                EVENT_TOOL_INVOKED,
                "Permission auto-allowed (allow all for this message)",
                {"request_id": request_id, "tool_title": tool_title},
            )
            return "allow_once"

        # 判定：本輪已拒絕全部（msg_conf 旗標）→ 直接拒絕（唔出卡）
        if self._is_permission_deny_all(message_id):
            msg_log().append_info_log(
                EVENT_TOOL_INVOKED,
                "Permission auto-denied (deny all for this message)",
                {"request_id": request_id, "tool_title": tool_title},
            )
            return "reject_once"

        # 上一請求殘留先清（request_id 核對係第二重保險——PHP 側亦核）
        self._remove_file_if_exists(permission_decision_file)

        # 寫 pending 請求（原子——PHP 讀到嘅永遠係完整 JSON）
        deadline_ts = int(time.time()) + PERMISSION_WAIT_TIMEOUT_SEC
        permission_request = {
            "request_id":  request_id,
            "tool_title":  tool_title,
            "tool_input":  tool_input if isinstance(tool_input, (dict, list)) else str(tool_input or ""),
            "created_at":  int(time.time()),
            "deadline_ts": deadline_ts,
        }
        self._write_json_atomic(permission_request_file, permission_request)
        msg_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "Permission requested: " + tool_title,
            {"request_id": request_id, "tool_title": tool_title},
        )

        # 等決定（每 tick 查一次決定檔；逾時＝自動 deny）
        while time.time() < deadline_ts:
            # 判定：使用者已取消 → 當拒絕收（watchdog 同時會終止連線）
            if msg_main().get_msg_status() == "cancelled":
                msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Permission wait cancelled by user")
                return "reject_once"

            decision = self._read_permission_decision(permission_decision_file, request_id)

            # 判定：使用者允許 → 回允許
            if decision == "allow":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed by user", {"request_id": request_id})
                return "allow_once"

            # 判定：使用者允許全部（本輪）→ 照放行（後續請求靠 msg_conf 旗標——唔再出卡）
            if decision == "allow_all":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed for this message (allow all)", {"request_id": request_id})
                return "allow_once"

            # 判定：使用者拒絕全部（本輪）→ 照拒（後續請求靠 msg_conf 旗標——唔再出卡）
            if decision == "deny_all":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied for this message (deny all)", {"request_id": request_id})
                return "reject_once"

            # 判定：使用者拒絕 → 回拒絕
            if decision == "deny":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied by user", {"request_id": request_id})
                return "reject_once"

            await asyncio.sleep(PERMISSION_DECISION_POLL_INTERVAL_SEC)

        # 逾時（無人決定）→ 本側代寫 deny（紀錄一體）＋回拒絕
        timeout_decision = {
            "request_id": request_id,
            "decision":   "deny",
            "source":     "timeout",
            "decided_at": int(time.time()),
        }
        self._write_json_atomic(permission_decision_file, timeout_decision)
        msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission timed out (auto deny)", {"request_id": request_id})

        return "reject_once"

    def _read_permission_decision(self, permission_decision_file: Path, request_id: str) -> str:
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

    def _is_read_only_permission(self, tool_title: str) -> bool:
        """讀類確認？（零寫入——加檔入對話等；直接放行唔出卡）。"""
        # 逐前綴核對（清單＝模組常數——新增讀類只改常數）
        for read_only_prefix in READ_ONLY_PERMISSION_PREFIXES:
            if tool_title.startswith(read_only_prefix):
                return True

        return False

    def _is_permission_allow_all(self, message_id: str) -> bool:
        """本輪已允許全部？（讀 msg_conf 旗標 permission_allow_all——每次問之前讀硬檔；缺／壞 → False）。"""
        permission_conf_file = msg_main().get_msg_file_path("conf", message_id)

        try:
            conf_data = json.loads(permission_conf_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return False

        # 判定：非 dict → 未允許
        if not isinstance(conf_data, dict):
            return False

        return bool(conf_data.get("permission_allow_all", False))

    def _is_permission_deny_all(self, message_id: str) -> bool:
        """本輪已拒絕全部？（讀 msg_conf 旗標 permission_deny_all——每次問之前讀硬檔；缺／壞 → False）。"""
        permission_conf_file = msg_main().get_msg_file_path("conf", message_id)

        try:
            conf_data = json.loads(permission_conf_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return False

        # 判定：非 dict → 未拒絕
        if not isinstance(conf_data, dict):
            return False

        return bool(conf_data.get("permission_deny_all", False))

    def _write_json_atomic(self, target_file: Path, json_payload: dict) -> None:
        """原子寫 JSON（tmp＋replace——讀者唔會見到半截檔）。"""
        temporary_file = target_file.with_suffix(target_file.suffix + ".tmp")
        temporary_file.write_text(json.dumps(json_payload, ensure_ascii=False), encoding="utf-8")
        os.replace(temporary_file, target_file)

    def _remove_file_if_exists(self, target_file: Path) -> None:
        """檔案存在即刪（殘留清理——唔存在＝正常）。"""
        try:
            target_file.unlink()
        except FileNotFoundError:
            pass

    def _acp_prompt_finished(self, prompt_response, stream_state: dict) -> None:
        """
        本輪結束（PromptResponse）——成功事件語義：
        用量記 RESPONSE_COMPLETED（成功事件）；stop_reason＝cancelled 除外（取消路徑唔當完成）。
        """
        stop_reason = str(getattr(prompt_response, "stop_reason", "") or "")

        # 判定：協定報 cancelled（本側曾發取消）→ 唔記完成（收尾交取消路徑）
        if stop_reason == "cancelled":
            msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "ACP turn cancelled")
            return

        usage = stream_state.get("usage", {})
        usage_metadata = {
            "total_tokens":  usage.get("used", 0),
            "context_size":  usage.get("size", 0),
            "cost_usd":      usage.get("cost_usd", 0),
        }
        msg_log().append_success_log(EVENT_RESPONSE_COMPLETED, "ACP stream completed", usage_metadata)
        msg_state_machine().apply_and_report(EVENT_RESPONSE_COMPLETED)

    def _acp_cancelled(self) -> None:
        """使用者取消（watchdog 測到）——記事件一次（終止緊隨其後）。"""
        msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — stopping aider_daemon.acp (ACP)")

    # ----------------------------------------------------------
    # 雜項
    # ----------------------------------------------------------

    def _append_msg_stdout(self, msg_stdout_file: Path, raw_line: str) -> None:
        """raw 行 append 寫入 msg_stdout（原樣——首寫即建立檔）。"""
        with msg_stdout_file.open("a", encoding="utf-8", errors="replace") as output_file:
            output_file.write(raw_line + "\n")

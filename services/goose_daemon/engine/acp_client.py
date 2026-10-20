#!/usr/bin/env python3
"""
acp_client.py

ACP 協定客戶端（acp_client_class singleton——get_instance() 取得）。

職責單一：拉 goose acp 子程序（stdio 協定——本 daemon 專用通道；命令由 session_request 帶入）
並驅動單次查詢——拉程序 → initialize → session 開或續 → session 模式 → prompt；
全程事件原樣交回呼（啞管道——零業務判斷；翻譯／權限決策一律喺呼叫端）。

唯一用法：run_session(session_request, callbacks)——同步入口
（內部 asyncio.run——一次工單一條連線、完即關）。

事件正規化（SDK update 類別 → 中立 kind；呼叫端唔需要認識 acp 套件）：
    AgentThoughtChunk        → "thought"（思考）     {"text", "message_id"}
    AgentMessageChunk        → "text"（主回答）      {"text", "message_id"}
    ToolCallStart／Progress  → "tool"（工具活動）    {"tool_call_id", "title", "message_id"}
    UsageUpdate              → "usage"（用量）       {"used", "size", "cost_usd"}
    其餘                      → "other"              {"update_dump"}

協定追蹤：全部收發訊息（JSON-RPC——incoming／outgoing）原樣經 on_stdio_line 出。

重播閘（續話引擎——如 goose——load 時會先將成條對話重播一次〔唔屬本輪回應〕）——
prompt 未出前嘅 session 事件唔入回應：內容類（text／thought）以 kind「replay」交回呼（獨立歸檔）；
其餘只數。協議留痕 observer 照舊；閘前事件數記 1 行。
"""

import asyncio
import json
import subprocess
import sys

import psutil

from acp import PROTOCOL_VERSION, connect_to_agent
from acp.schema import (
    AgentMessageChunk,
    AgentThoughtChunk,
    AllowedOutcome,
    ClientCapabilities,
    Implementation,
    RequestPermissionResponse,
    TextContentBlock,
    ToolCallProgress,
    ToolCallStart,
    UsageUpdate,
)


# 取消檢查間隔（秒——watchdog 每 tick 問一次 is_cancelled）
CANCEL_CHECK_INTERVAL_SEC = 1.0

# 收尾等待（秒——連線關後等 goose 自然退；逾時＝樹狀終止）
SHUTDOWN_WAIT_TIMEOUT_SEC = 5.0

# 權限選項 id（goose 選項中本側只用兩態——允許一次／拒絕一次）
PERMISSION_OPTION_ALLOW = "allow_once"
PERMISSION_OPTION_REJECT = "reject_once"

# 客戶端標識（initialize 報名——協定要求）
CLIENT_INFO_NAME = "sc-hkai"
CLIENT_INFO_TITLE = "HKAI"
CLIENT_INFO_VERSION = "1.0.0"


class acp_client_class:
    """
    ACP 客戶端（singleton——整個程序只有一個實例）。

    職責單一：單次 ACP 連線嘅協定驅動（見檔頭）；不含翻譯／權限政策。
    """

    _instance: "acp_client_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "acp_client_class":
        """取得 acp_client_class 單例。"""
        return cls()

    def run_session(self, session_request: dict, callbacks: dict) -> None:
        """
        同步入口：跑單次 ACP 查詢（回傳＝無；例外向上拋畀呼叫端收尾）。

        session_request（dict——本次連線參數）：
            agent_command       agent 子程序命令（list——含引數；goose＝[執行檔, "acp"]）
            agent_env           子程序環境（完整 env）
            project_path        工作目錄（ACP cwd——真實專案根）
            session_title       session 名（每對話一條——同名續話；無續話引擎＝空）
            prompt_text         本輪查詢文字
            session_mode        session 模式（UI 三模查表值——空＝唔設（session 默認））
            resume_session      續話與否（True＝同名續話；False＝直接開新）
            session_history     隨新 session 帶入嘅歷史（[{role, content}]——resume_session=False 用；無＝None）
        callbacks（dict——事件回呼；除 on_permission_request 為 async 外，其餘同步）：
            on_stdio_line(line)                      原始輸出行（協定追蹤＋stderr——純紀錄）
            on_ready(session_id)                     連線＋session＋模式已備（尚未查詢）
            on_update(update_kind, payload)          session 事件（正規化——見檔頭）
            on_permission_request(tool_call) -> str  async——權限請求（回選項 id）
            on_prompt_finished(prompt_response)      本輪正常結束（PromptResponse）
            on_cancelled()                           使用者取消已測到（終止前記一次）
            is_cancelled() -> bool                   使用者已取消？（watchdog 每秒問）
        """
        asyncio.run(self._run_session(session_request, callbacks))

    # ----------------------------------------------------------
    # 協定主流程
    # ----------------------------------------------------------

    async def _run_session(self, session_request: dict, callbacks: dict) -> None:
        """拉程序 → 交協定握手 → 查詢 → 收尾（run_session 唯一呼叫者）。"""
        # 解包（同名變數——一眼知來源 key）
        agent_command   = session_request["agent_command"]
        agent_env       = session_request["agent_env"]
        project_path    = session_request["project_path"]
        session_title   = session_request["session_title"]
        prompt_text     = session_request["prompt_text"]
        session_mode    = session_request["session_mode"]
        resume_session  = session_request["resume_session"]
        session_history = session_request["session_history"]

        on_stdio_line         = callbacks["on_stdio_line"]
        on_ready              = callbacks["on_ready"]
        on_update             = callbacks["on_update"]
        on_permission_request = callbacks["on_permission_request"]
        on_prompt_finished    = callbacks["on_prompt_finished"]
        on_cancelled          = callbacks["on_cancelled"]
        is_cancelled          = callbacks["is_cancelled"]

        agent_process = await self._spawn_agent_process(agent_command, agent_env, project_path)
        stderr_task   = asyncio.create_task(self._drain_stderr(agent_process, on_stdio_line))
        watchdog_task = None
        connection    = None

        try:
            # 連線（stdio——協定收發；observer 全程原樣留痕）
            session_client    = _acp_session_client(on_update, on_permission_request)
            protocol_observer = self._make_protocol_observer(on_stdio_line)

            connection = connect_to_agent(
                session_client,
                agent_process.stdin,
                agent_process.stdout,
                observers=[protocol_observer],
            )

            # initialize（協定版本＋客戶端資訊）
            await connection.initialize(
                protocol_version=PROTOCOL_VERSION,
                client_capabilities=ClientCapabilities(),
                client_info=Implementation(
                    name=CLIENT_INFO_NAME,
                    title=CLIENT_INFO_TITLE,
                    version=CLIENT_INFO_VERSION,
                ),
            )

            # session 開或續（同名續話／直接開新——見 _resolve_session）
            session_id = await self._resolve_session(connection, project_path, session_title, resume_session, session_history, on_stdio_line)

            # 模式（查表值——工具權限 defer 交本側；空＝唔設（session 默認）；失敗＝env 已兜底 → 記行繼續）
            if session_mode != "":
                try:
                    await connection.set_session_mode(session_id=session_id, mode_id=session_mode)
                except Exception as exc:  # noqa: BLE001
                    on_stdio_line("[acp] set session mode failed: " + str(exc))

            # 就緒（連線＋session＋模式已備——未查詢）
            on_ready(session_id)

            # 取消 watchdog（背住跑——測到取消即通知＋終止）
            watchdog_task = asyncio.create_task(
                self._watch_cancellation(connection, session_id, agent_process, on_cancelled, is_cancelled)
            )

            # 開重播閘（load 重播完結——再嚟嘅才係本輪回應）
            dropped_update_count = session_client.release_replay_guard()

            # 判定：重播期有事件 → 記行（純紀錄）
            if dropped_update_count > 0:
                on_stdio_line("[acp] load replay separated: " + str(dropped_update_count) + " updates")

            # 查詢（阻塞至本輪結束——逐事件由回呼處理）
            prompt_response = await connection.prompt(
                session_id=session_id,
                prompt=[TextContentBlock(type="text", text=prompt_text)],
            )
            on_prompt_finished(prompt_response)

        finally:
            # 收尾（任何一步失敗都唔阻其餘）
            await self._teardown_session(connection, agent_process, watchdog_task, stderr_task)

    async def _spawn_agent_process(self, agent_command: list, agent_env: dict, project_path: str):
        """拉 agent 子程序（ACP over stdio——goose／aider_daemon.acp 同一機制；stderr 獨立）；Windows 無視窗。"""
        subprocess_kwargs = {}

        # 判定：Windows → 無視窗（console 程式會被開黑窗）
        if sys.platform == "win32":
            subprocess_kwargs["creationflags"] = subprocess.CREATE_NO_WINDOW

        agent_process = await asyncio.create_subprocess_exec(
            *agent_command,
            cwd=project_path,
            env=agent_env,
            stdin=asyncio.subprocess.PIPE,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE,
            **subprocess_kwargs,
        )

        return agent_process

    async def _drain_stderr(self, agent_process, on_stdio_line) -> None:
        """背景逐行讀 stderr（原樣交紀錄；EOF 即結）。"""
        try:
            while True:
                raw_bytes = await agent_process.stderr.readline()

                # 判定：EOF → 收工
                if raw_bytes == b"":
                    break

                stderr_line = raw_bytes.decode("utf-8", errors="replace").rstrip("\r\n")
                on_stdio_line("[stderr] " + stderr_line)
        except Exception:  # noqa: BLE001
            pass

    async def _resolve_session(self, connection, project_path: str, session_title: str, resume_session: bool, session_history, on_stdio_line) -> str:
        """session 開或續：resume_session=False＝直接建新
        （歷史〔session_history〕隨 session/new meta 帶入）；True＝清單同名續話，搵唔到（或續失敗）→ 建新（回 session_id）。"""
        # 判定：唔續話（引擎無 session 清單／持久化）→ 直接建新 session（歷史隨 meta 帶入）
        if resume_session is False:
            new_session_response = await connection.new_session(
                cwd=project_path,
                mcp_servers=[],
                history=session_history,
            )
            on_stdio_line("[acp] session created (no resume)")

            return new_session_response.session_id

        # 清單查詢唔落 query 參數——goose 嗰個 query 係搜「訊息內文」（唔係搜 session 名）；
        # 同名判定一律靠下面 title 比對（title＝session 名）
        try:
            list_response = await connection.list_sessions(cwd=project_path)
        except Exception as exc:  # noqa: BLE001
            on_stdio_line("[acp] session list failed: " + str(exc))
            list_response = None

        # 判定：清單搵到同名 session → 續話（load 失敗＝落到建新）
        if list_response is not None:
            for session_info in list_response.sessions:
                # 判定：標題唔對 → 跳過
                if str(session_info.title or "") != session_title:
                    continue

                try:
                    await connection.load_session(cwd=project_path, session_id=session_info.session_id, mcp_servers=[])
                    on_stdio_line("[acp] session resumed: " + session_title)
                    return session_info.session_id
                except Exception as exc:  # noqa: BLE001
                    on_stdio_line("[acp] session load failed: " + str(exc))
                    break

        new_session_response = await connection.new_session(
            cwd=project_path,
            mcp_servers=[],
            sessionTitle=session_title,
        )
        on_stdio_line("[acp] session created: " + session_title)

        return new_session_response.session_id

    async def _watch_cancellation(self, connection, session_id: str, agent_process, on_cancelled, is_cancelled) -> None:
        """取消 watchdog：每 tick 問 is_cancelled——成立＝記一次 → 通知協定 → 終止程序樹。"""
        while True:
            await asyncio.sleep(CANCEL_CHECK_INTERVAL_SEC)

            try:
                user_cancelled = bool(is_cancelled())
            except Exception:  # noqa: BLE001
                user_cancelled = False

            # 判定：未取消 → 等下一 tick
            if not user_cancelled:
                continue

            on_cancelled()

            # 協定取消通知（agent 收到即停下——隨後仍終止程序樹）
            try:
                await connection.cancel(session_id=session_id)
            except Exception:  # noqa: BLE001
                pass

            await self._terminate_process_tree(agent_process)

            return

    async def _teardown_session(self, connection, agent_process, watchdog_task, stderr_task) -> None:
        """收尾：watchdog 停 → 連線關（stdin EOF）→ 等 agent 自然退（逾時＝樹狀終止）→ stderr 收線。"""
        await self._cancel_task(watchdog_task)

        # 判定：連線已建 → 關（stdin EOF——goose 自然退場前奏）
        if connection is not None:
            try:
                await connection.close()
            except Exception:  # noqa: BLE001
                pass

        # 判定：agent 未退 → 先等自然退；逾時＝樹狀終止
        if agent_process.returncode is None:
            try:
                await asyncio.wait_for(agent_process.wait(), timeout=SHUTDOWN_WAIT_TIMEOUT_SEC)
            except asyncio.TimeoutError:
                await self._terminate_process_tree(agent_process)

        await self._cancel_task(stderr_task)

    async def _terminate_process_tree(self, agent_process) -> None:
        """安全終止 agent 程序（含子孫——先客氣後硬殺；已退＝直返）。"""
        # 判定：已退 → 直返
        if agent_process.returncode is not None:
            return

        # 子孫清單（recursive——agent 工具呼叫可能開咗子程序）
        try:
            descendant_processes = psutil.Process(agent_process.pid).children(recursive=True)
        except psutil.Error:
            descendant_processes = []

        # 先客氣（子孫＋本體 terminate）
        for descendant_process in descendant_processes:
            try:
                descendant_process.terminate()
            except psutil.Error:
                pass

        try:
            agent_process.terminate()
        except ProcessLookupError:
            pass

        # 逾時未死 → 硬殺（子孫＋本體）
        try:
            await asyncio.wait_for(agent_process.wait(), timeout=SHUTDOWN_WAIT_TIMEOUT_SEC)
            return
        except asyncio.TimeoutError:
            pass

        for descendant_process in descendant_processes:
            try:
                descendant_process.kill()
            except psutil.Error:
                pass

        try:
            agent_process.kill()
        except ProcessLookupError:
            pass

        await agent_process.wait()

    async def _cancel_task(self, background_task) -> None:
        """取消並等待背景 task 收線（吞取消例外——正常收尾語義）。"""
        # 判定：無 task 或已完成 → 直返
        if background_task is None:
            return
        if background_task.done():
            return

        background_task.cancel()

        try:
            await background_task
        except asyncio.CancelledError:
            pass
        except Exception:  # noqa: BLE001
            pass

    # ----------------------------------------------------------
    # 協定追蹤（收發全留痕——純紀錄）
    # ----------------------------------------------------------

    def _make_protocol_observer(self, on_stdio_line):
        """協定追蹤 observer：全部 JSON-RPC 收發訊息 → on_stdio_line（incoming／outgoing）。"""
        def protocol_observer(stream_event) -> None:
            direction = stream_event.direction.value if isinstance(stream_event.direction, StreamDirection) else str(stream_event.direction)
            on_stdio_line("[acp " + direction + "] " + _dump_protocol_message(stream_event.message))

        return protocol_observer


class _acp_session_client:
    """
    Client 介面實作（SDK 結構式——只實作本側需要嘅兩支）：
        session_update     → 交 on_update（正規化後——見檔頭對照表；
                              重播閘開前〔prompt 未出〕內容類以「replay」kind 交、其餘只數
                              ——見 release_replay_guard）
        request_permission → 交 on_permission_request（async——等使用者決定後回選項 id）
    其餘方法缺席：SDK router 自動以 method_not_found（fs 讀寫）／optional 默認值（terminal／elicitation）答覆。
    """

    def __init__(self, on_update, on_permission_request):
        self._on_update = on_update
        self._on_permission_request = on_permission_request

        # 重播閘（初始＝閘前狀態；prompt 一出才開——見 release_replay_guard）
        self._is_replay_guard_active = True
        self._dropped_update_count = 0

    async def session_update(self, session_id: str, update, **kwargs) -> None:
        """session 事件（notification——重播閘開後逐個交呼叫端；閘前內容類另交 replay 頻道）。"""
        update_kind, update_payload = _normalize_update(update)

        # 判定：重播閘未開（prompt 未出——load 重播期）→ 唔當本輪回應
        if self._is_replay_guard_active is True:
            self._dropped_update_count = self._dropped_update_count + 1

            # 判定：內容類（text／thought）→ 交 replay 頻道（呼叫端獨立歸檔；唔入回應檔）
            if update_kind in ("text", "thought"):
                self._on_update("replay", update_payload)

            return

        self._on_update(update_kind, update_payload)

    def release_replay_guard(self) -> int:
        """開重播閘（prompt 一出＝本輪回應開始）；回傳閘前丟棄數（供記行）。"""
        self._is_replay_guard_active = False
        return self._dropped_update_count

    async def request_permission(self, session_id: str, tool_call, options, **kwargs) -> RequestPermissionResponse:
        """權限請求（goose defer 出嚟）——交呼叫端（async）等決定 → 回協定選項。"""
        option_id = await self._on_permission_request(tool_call)

        # 判定：選項 id 缺漏 → 保守拒（拒＝安全側——同逾時語義一致）
        if option_id == "":
            option_id = PERMISSION_OPTION_REJECT

        return RequestPermissionResponse(outcome=AllowedOutcome(outcome="selected", option_id=option_id))


# ----------------------------------------------------------
# 事件正規化（SDK 類別 → 中立 kind——見檔頭對照表）
# ----------------------------------------------------------

def _normalize_update(update) -> tuple:
    """SDK update 類別 → (kind, payload)。"""
    # 思考段（reasoning chunk）
    if isinstance(update, AgentThoughtChunk):
        return "thought", {"text": _extract_chunk_text(update), "message_id": _extract_message_id(update)}

    # 主回答段（message chunk）
    if isinstance(update, AgentMessageChunk):
        return "text", {"text": _extract_chunk_text(update), "message_id": _extract_message_id(update)}

    # 工具活動（開始／進度）
    if isinstance(update, (ToolCallStart, ToolCallProgress)):
        return "tool", {
            "tool_call_id": str(getattr(update, "tool_call_id", "") or ""),
            "title":        str(getattr(update, "title", "") or ""),
            "message_id":   _extract_message_id(update),
        }

    # 用量更新（goose 報 context 用量／成本）
    if isinstance(update, UsageUpdate):
        return "usage", _extract_usage(update)

    # 其餘（plan／commands／mode 變更等）——原樣轉存
    return "other", {"update_dump": _dump_protocol_message(update.model_dump(by_alias=True))}


def _extract_chunk_text(update) -> str:
    """抽 chunk 文字（content 為文字塊——非文字／缺＝空字串）。"""
    content_block = getattr(update, "content", None)

    # 判定：無內容 → 空字串
    if content_block is None:
        return ""

    return str(getattr(content_block, "text", "") or "")


def _extract_message_id(update) -> str:
    """抽訊息 id（_meta.messageId——段落 index 判定用；缺＝空字串）。"""
    update_meta = getattr(update, "field_meta", None)

    # 判定：無 _meta → 空字串
    if not isinstance(update_meta, dict):
        return ""

    return str(update_meta.get("messageId", "") or "")


def _extract_usage(update) -> dict:
    """抽用量（used／size／cost——成本只取金額；引擎報 USD）。"""
    usage_payload = {
        "used":     getattr(update, "used", 0),
        "size":     getattr(update, "size", 0),
        "cost_usd": 0,
    }

    cost = getattr(update, "cost", None)

    # 判定：有成本物件 → 取金額
    if cost is not None:
        usage_payload["cost_usd"] = getattr(cost, "amount", 0)

    return usage_payload


def _dump_protocol_message(protocol_message) -> str:
    """序列化單條協定訊息（失敗＝後備 repr——紀錄唔准斷）。"""
    try:
        return json.dumps(protocol_message, ensure_ascii=False, default=str)
    except Exception:  # noqa: BLE001
        return repr(protocol_message)

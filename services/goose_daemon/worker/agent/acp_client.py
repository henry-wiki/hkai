#!/usr/bin/env python3
# pyright: reportAny=false, reportExplicitAny=false
"""
acp_client.py

ACP 協定客戶端（acp_client singleton——get_instance() 取得）。

職責單一：拉 goose acp 子程序（stdio 協定——本 daemon 專用通道；命令由 session_request 帶入）
並驅動單次查詢——拉程序 → initialize → session 開或續 → session 模式 → prompt；
全程事件原樣交回呼（啞管道——零業務判斷；翻譯／權限決策一律喺呼叫端）。

唯一用法：execute_session(session_request, callbacks)——非同步入口（由 session 以 asyncio.run 驅動）；
一次工單一條連線、完即關。

事件正規化（SDK update 類別 → 中立 kind；呼叫端唔需要認識 acp 套件）：
    AgentThoughtChunk        → "thought"（思考）     {"text", "agent_turn_id"}
    AgentMessageChunk        → "text"（主回答）      {"text", "agent_turn_id"}
    ToolCallStart／Progress  → "tool"（工具活動）    {"tool_call_id", "title", "agent_turn_id"}
    UsageUpdate              → "usage"（用量）       {"used", "size", "cost_usd"}
    其餘                      → "other"              {"update_dump"}

協定追蹤：全部收發訊息（JSON-RPC——incoming／outgoing）原樣經 on_stdio_line 出。

重播閘（續話agent——如 goose——load 時會先將成條對話重播一次〔唔屬本輪回應〕）——
prompt 未出前嘅 session 事件唔入回應：內容類（text／thought）以 kind「replay」交回呼（獨立歸檔）；
其餘只數。協議留痕 observer 照舊；閘前事件數記 1 行。
"""

import asyncio
import contextlib
import subprocess
import sys
from collections.abc import Awaitable, Callable
from typing import TypedDict, cast

import psutil
from acp import PROTOCOL_VERSION, connect_to_agent
from acp.client.connection import ClientSideConnection
from acp.connection import StreamEvent
from acp.schema import (
    ClientCapabilities,
    ImageContentBlock,
    Implementation,
    TextContentBlock,
)

from ...runtime import heartbeat_agent
from .acp_session_client import acp_session_client, dump_protocol_message

# 取消檢查間隔（秒——watchdog 每 tick 問一次 is_cancelled）
CANCEL_CHECK_INTERVAL_SEC = 1.0

# 收尾等待（秒——連線關後等 goose 自然退；逾時＝樹狀終止）
SHUTDOWN_WAIT_TIMEOUT_SEC = 6.0

# terminate 後寬限（秒——未死即 kill）
KILL_WAIT_TIMEOUT_SEC = 2.0

# 客戶端標識（initialize 報名——協定要求）
CLIENT_INFO_NAME = "sc-hkai"
CLIENT_INFO_TITLE = "HKAI"
CLIENT_INFO_VERSION = "1.0.0"


# ----------------------------------------------------------
# 契約型別（session 產生、本檔消費——單一來源）
# ----------------------------------------------------------

class PromptImage(TypedDict):
    """圖片（prompt 附件）"""

    mime_type: str
    data_base64: str


class SessionRequest(TypedDict):
    """本次連線參數（session.prepare_session 產生）"""

    agent_command: list[str]
    agent_env: dict[str, str]
    project_path: str
    session_title: str
    prompt_text: str
    prompt_images: list[PromptImage]
    session_mode: str
    resume_session: bool
    session_history: list[dict[str, str]] | None
    provider_name: str
    model_name: str
    reasoning_effort: str


class SubprocessKwargs(TypedDict, total=False):
    """Windows 專用 spawn 參數（POSIX＝空 dict）"""

    creationflags: int


class SessionCallbacks(TypedDict):
    """事件回呼（session.build_callbacks 產生）"""

    on_stdio_line: Callable[[str], None]
    on_ready: Callable[[str], None]
    on_update: Callable[[str, dict[str, object]], None]
    on_permission_request: Callable[[object], Awaitable[str]]
    on_prompt_finished: Callable[[object], None]
    on_cancelled: Callable[[], None]
    is_cancelled: Callable[[], bool]


class acp_client:
    """
    ACP 客戶端（singleton——整個程序只有一個實例）。

    職責單一：單次 ACP 連線嘅協定驅動（見檔頭）；不含翻譯／權限政策。
    """

    _instance: "acp_client | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "acp_client":
        """取得 acp_client 單例。"""
        return cls()

    # ----------------------------------------------------------
    # 協定主流程
    # ----------------------------------------------------------

    async def execute_session(self, session_request: SessionRequest, callbacks: SessionCallbacks) -> None:
        """拉程序 → 交協定握手 → 查詢 → 收尾（非同步；呼叫端跑 event loop）。"""
        # 解包（同名變數——一眼知來源 key）
        agent_command   = session_request["agent_command"]
        agent_env       = session_request["agent_env"]
        project_path    = session_request["project_path"]
        session_title   = session_request["session_title"]
        prompt_text     = session_request["prompt_text"]
        prompt_images   = session_request.get("prompt_images", [])
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

        # 拉agent（回傳進程控制代碼）＋背景排空 stderr（防管道塞死）
        agent_process = await self._spawn_agent_process(agent_command, agent_env, project_path)

        # agent pid 一出即刻寫 goose.<pid>.json（生存證明——同心跳同契約）
        heartbeat_agent.init(agent_process.pid)

        stderr_task   = asyncio.create_task(self._drain_stderr(agent_process, on_stdio_line))
        watchdog_task = None
        connection    = None
        session_id    = ""

        try:
            # 連線（stdio——協定收發；observer 全程原樣留痕）
            session_client    = acp_session_client(on_update, on_permission_request)
            protocol_observer = self._make_protocol_observer(on_stdio_line)

            # 客戶端實作符合協定（結構性）——pyright 對 protocol 推斷不足，此行忽略
            connection = connect_to_agent(
                session_client,  # pyright: ignore[reportArgumentType]
                agent_process.stdin,
                agent_process.stdout,
                observers=[protocol_observer],
            )

            # initialize（協定版本＋客戶端資訊）
            _ = await connection.initialize(
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
                    _ = await connection.set_session_mode(session_id=session_id, mode_id=session_mode)
                except Exception as exc:  # noqa: BLE001
                    on_stdio_line("[acp] set session mode failed: " + str(exc))

            # 官方 config 選項（provider／model／thinking effort——換 model 唔使改 DB）
            await self._apply_config_options(connection, session_id, session_request, on_stdio_line)

            # 就緒（連線＋session＋模式已備——未查詢）
            on_ready(session_id)

            # 取消 watchdog（背住跑——測到取消即通知＋終止）
            watchdog_task = asyncio.create_task(
                self._watch_cancellation(connection, session_id, on_cancelled, is_cancelled, on_stdio_line)
            )

            # 開重播閘（load 重播完結——再嚟嘅才係本輪回應）
            dropped_update_count = session_client.release_replay_guard()

            # 判定：重播期有事件 → 記行（純紀錄）
            if dropped_update_count > 0:
                on_stdio_line("[acp] load replay separated: " + str(dropped_update_count) + " updates")

            # 查詢（阻塞至本輪結束——逐事件由回呼處理）
            # 提示內容塊（文字＋圖片——圖片以 base64 原樣交模型）
            prompt_blocks: list[object] = [TextContentBlock(type="text", text=prompt_text)]

            # 逐張圖片加 image block
            for prompt_image_item in prompt_images:
                prompt_blocks.append(ImageContentBlock(
                    type="image",
                    mime_type=prompt_image_item["mime_type"],
                    data=prompt_image_item["data_base64"],
                ))

            prompt_response = await connection.prompt(
                session_id=session_id,
                prompt=prompt_blocks,  # pyright: ignore[reportArgumentType]
            )
            on_prompt_finished(prompt_response)

        finally:
            # 收尾（任何一步失敗都唔阻其餘）
            await self._teardown_session(connection, session_id, agent_process, watchdog_task, stderr_task, on_stdio_line)

    async def _spawn_agent_process(self, agent_command: list[str], agent_env: dict[str, str], project_path: str) -> asyncio.subprocess.Process:
        """拉 agent 子程序（ACP over stdio——stderr 獨立）；Windows 無視窗。"""
        subprocess_kwargs: SubprocessKwargs = {}

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

    async def _drain_stderr(self, agent_process: asyncio.subprocess.Process, on_stdio_line: Callable[[str], None]) -> None:
        """
        排空 stderr 管道（drain＝排走／抽乾：持續把管道內容抽走，唔留積水）。

        作用：背景逐行讀agent stderr（唔阻塞主流程）——原樣交 on_stdio_line 寫入 job_stdout 檔；
              唔判斷對錯、唔過濾。管道排空係必要：buffer（~64KB）寫滿後agent會卡死喺寫入。
        EOF（agent收工）或管道已斷 ＝ 正常結束。
        """
        stderr_stream = agent_process.stderr

        # 判定：無 stderr 管（唔應該發生——spawn 一定 PIPE）→ 唔讀
        if stderr_stream is None:
            return

        try:
            while True:
                raw_bytes = await stderr_stream.readline()

                # 判定：EOF → 收工
                if raw_bytes == b"":
                    break

                stderr_line = raw_bytes.decode("utf-8", errors="replace").rstrip("\r\n")
                on_stdio_line("[stderr] " + stderr_line)
        except Exception as exc:  # noqa: BLE001
            # 例外（刻意盲捕——stderr 管已斷，讀唔到屬正常收尾；記一行留痕）
            on_stdio_line("[stderr] drain ended: " + str(exc))

    async def _resolve_session(self, connection: ClientSideConnection, project_path: str, session_title: str, resume_session: bool, session_history: list[dict[str, str]] | None, on_stdio_line: Callable[[str], None]) -> str:
        """session 開或續：resume_session=False＝直接建新
        （歷史〔session_history〕隨 session/new meta 帶入）；True＝清單同名續話，搵唔到（或續失敗）→ 建新（回 session_id）。"""
        # 判定：唔續話（agent無 session 清單／持久化）→ 直接建新 session（歷史隨 meta 帶入）
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
                    _ = await connection.load_session(cwd=project_path, session_id=session_info.session_id, mcp_servers=[])
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

    async def _apply_config_options(self, connection: ClientSideConnection, session_id: str, session_request: SessionRequest, on_stdio_line: Callable[[str], None]) -> None:
        """套用官方 session config 選項（provider／model／thinking_effort——ACP session/set_config_option）。

        值＝空 → 唔設（沿用 goose 當前）；失敗 → 記行繼續（工具權限／回覆照行）。
        """
        thinking_effort_values = ["off", "low", "medium", "high", "max"]
        reasoning_effort = str(session_request.get("reasoning_effort", ""))

        # 判定：不是官方值 → 唔設（避免 goose 收到非法選項）
        if reasoning_effort not in thinking_effort_values:
            reasoning_effort = ""

        config_values = {
            "provider": str(session_request.get("provider_name", "")),
            "model": str(session_request.get("model_name", "")),
            "thinking_effort": reasoning_effort,
        }

        for config_id, config_value in config_values.items():
            # 判定：空值 → 唔設（沿用 session 現值）
            if config_value == "":
                continue

            try:
                _ = await connection.set_config_option(
                    config_id=config_id,
                    session_id=session_id,
                    value=config_value,
                )
            except Exception as exc:  # noqa: BLE001
                on_stdio_line("[acp] set config option failed: " + config_id + "=" + config_value + " (" + str(exc) + ")")

    async def _watch_cancellation(self, connection: ClientSideConnection, session_id: str, on_cancelled: Callable[[], None], is_cancelled: Callable[[], bool], on_stdio_line: Callable[[str], None]) -> None:
        """取消 watchdog：每 tick 問 is_cancelled——成立＝記一次 → 送 session/cancel（收尾交 teardown 按 6 秒規則）。"""
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

            # 協定取消通知（官方正路——agent收到即停本輪；程序退場交 teardown）
            try:
                await connection.cancel(session_id=session_id)
                on_stdio_line("[acp] session cancel sent")
            except Exception as exc:  # noqa: BLE001
                on_stdio_line("[acp] session cancel failed: " + str(exc))

            return

    async def _close_agent_stdin(self, agent_process: asyncio.subprocess.Process, on_stdio_line: Callable[[str], None]) -> None:
        """關agent stdin（EOF＝agent收工信號；已關／冇管＝略過）。"""
        stdin_stream = agent_process.stdin

        # 判定：冇 stdin 管 → 唔使關
        if stdin_stream is None:
            return

        # 判定：已經關咗 → 唔重複
        if stdin_stream.is_closing() is True:
            return

        stdin_stream.close()
        await stdin_stream.wait_closed()
        on_stdio_line("[acp] agent stdin closed")


    async def _close_acp_session(self, connection: ClientSideConnection | None, session_id: str, on_stdio_line: Callable[[str], None]) -> None:
        """送官方 session/close（冇連線／冇 session 名＝略過）。"""
        # 判定：連線未建立 → 唔使關
        if connection is None:
            return

        # 判定：session 名無效 → 唔使關
        if session_id == "":
            return

        try:
            _ = await connection.close_session(session_id=session_id)
            on_stdio_line("[acp] session closed (session/close)")
        except Exception as exc:  # noqa: BLE001
            on_stdio_line("[acp] session close failed: " + str(exc))


    async def _teardown_session(self, connection: ClientSideConnection | None, session_id: str, agent_process: asyncio.subprocess.Process, watchdog_task: "asyncio.Task[None] | None", stderr_task: "asyncio.Task[None]", on_stdio_line: Callable[[str], None]) -> None:
        """收尾：watchdog 停 → 官方 session/close → 關連線 → 逐秒等agent退（6 秒）→ 未退＝強殺 → stderr 收線。"""
        heartbeat_agent.remove_heartbeat_file()

        await self._cancel_task(watchdog_task)

        # 官方關 session（ACP session/close——唔關＝agent唔會自己退）
        await self._close_acp_session(connection, session_id, on_stdio_line)

        # 判定：連線已建 → 關（stdin EOF——agent自然退場前奏）
        if connection is not None:
            try:
                await connection.close()
                on_stdio_line("[acp] connection closed")

                # 官方關 session 之後仍要關agent stdin（EOF 訊號——agent據此收工）
                await self._close_agent_stdin(agent_process, on_stdio_line)
            except Exception as exc:  # noqa: BLE001
                on_stdio_line("[acp] connection close failed: " + str(exc))

        agent_exited = await self._wait_for_agent_exit(agent_process, on_stdio_line)

        # 判定：6 秒未退 → 強殺（先記 log——要知係逾時先殺）
        if agent_exited is False:
            on_stdio_line("[acp] graceful timeout " + str(int(SHUTDOWN_WAIT_TIMEOUT_SEC)) + "s — force kill")
            await self._terminate_process_tree(agent_process, on_stdio_line)

        await self._cancel_task(stderr_task)

    async def _wait_for_agent_exit(self, agent_process: asyncio.subprocess.Process, on_stdio_line: Callable[[str], None]) -> bool:
        """逐秒等agent退（最多 SHUTDOWN_WAIT_TIMEOUT_SEC 秒；回傳有冇退）。"""
        elapsed_seconds = 0

        # 逐秒檢查（唔一次過等——要知幾時退）
        while elapsed_seconds < SHUTDOWN_WAIT_TIMEOUT_SEC:
            # 判定：已退 → 記 log 收工
            if agent_process.returncode is not None:
                on_stdio_line("[acp] agent exited after " + str(elapsed_seconds) + "s")
                return True

            await asyncio.sleep(1.0)
            elapsed_seconds = elapsed_seconds + 1

        agent_exited = agent_process.returncode is not None

        # 判定：最後一刻退咗 → 一樣記 log
        if agent_exited is True:
            on_stdio_line("[acp] agent exited after " + str(elapsed_seconds) + "s")

        return agent_exited

    async def _terminate_process_tree(self, agent_process: asyncio.subprocess.Process, on_stdio_line: Callable[[str], None]) -> None:
        """安全終止 agent 程序（含子孫——先客氣後硬殺；已退＝直返；每步記 log）。"""
        # 判定：已退 → 直返
        if agent_process.returncode is not None:
            return

        # 子孫清單（recursive——agent 工具呼叫可能開咗子程序）
        try:
            descendant_processes = cast(
                "list[psutil.Process]",
                psutil.Process(agent_process.pid).children(recursive=True),
            )
        except psutil.Error:
            descendant_processes = []

        on_stdio_line("[acp] terminate process tree (descendants=" + str(len(descendant_processes)) + ")")

        # 先客氣（子孫＋本體 terminate）
        for descendant_process in descendant_processes:
            with contextlib.suppress(psutil.Error):
                descendant_process.terminate()

        with contextlib.suppress(ProcessLookupError):
            agent_process.terminate()

        # 逾時未死 → 硬殺（子孫＋本體）
        try:
            _ = await asyncio.wait_for(agent_process.wait(), timeout=KILL_WAIT_TIMEOUT_SEC)
            on_stdio_line("[acp] agent terminated")
            return
        except asyncio.TimeoutError:
            pass

        on_stdio_line("[acp] terminate grace " + str(int(KILL_WAIT_TIMEOUT_SEC)) + "s expired — kill")

        for descendant_process in descendant_processes:
            with contextlib.suppress(psutil.Error):
                descendant_process.kill()

        with contextlib.suppress(ProcessLookupError):
            agent_process.kill()

        _ = await agent_process.wait()
        on_stdio_line("[acp] agent killed")

    async def _cancel_task(self, background_task: "asyncio.Task[None] | None") -> None:
        """取消並等待背景 task 收線（吞取消例外——正常收尾語義）。"""
        # 判定：無 task 或已完成 → 直返
        if background_task is None:
            return
        if background_task.done():
            return

        _ = background_task.cancel()

        try:
            await background_task
        except asyncio.CancelledError:
            pass
        except Exception:  # noqa: BLE001, S110
            # 例外（task 已取消／已結束——收線語義；刻意靜默）
            pass

    # ----------------------------------------------------------
    # 協定追蹤（收發全留痕——純紀錄）
    # ----------------------------------------------------------

    def _make_protocol_observer(self, on_stdio_line: Callable[[str], None]) -> Callable[[StreamEvent], None]:
        """協定追蹤 observer：全部 JSON-RPC 收發訊息 → on_stdio_line（incoming／outgoing）。"""
        def protocol_observer(stream_event: StreamEvent) -> None:
            direction = stream_event.direction.value
            on_stdio_line("[acp " + direction + "] " + dump_protocol_message(stream_event.message))

        return protocol_observer

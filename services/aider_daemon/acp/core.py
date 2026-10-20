#!/usr/bin/env python3
"""
core.py

ACP agent 本體（acp_agent_class——單例）：將本機 aider 包成 ACP server 側。

核心職責（協議最低三件＋取消）：
    initialize   協定版本／能力（load_session=False——v0 冇 session 持久化）
    new_session  開對話（記 cwd＋client 帶入嘅歷史與系統指示；coder 首次 prompt 才建——握手零失敗面）
    prompt       ACP 訊息 → aider run_stream → 分段串流返 client
                 （思考段 → thought 頻道；主回答 → text 頻道；完結＝標準用量事件 UsageUpdate）
    cancel       落取消旗標（aider 迴圈喺下一個分段邊界收手）

串流模型：aider 阻塞生成器跑喺 worker thread；每段經 run_coroutine_threadsafe
交返 asyncio loop 出 session/update（順序＝出廠順序）。權限卡同理（反向 request）。

分段：aider 會將思考段連標記寫入文字流（aider/reasoning_tags.py 嘅輸出形——開「► **THINKING**」、
收「► **ANSWER**」）；本側按標記拆返兩頻道（thought／text）。標記值喺 _build_coder 時向 aider 讀，
唔硬編。本輪完結另出標準用量事件（UsageUpdate——aider 統計；沒有數字＝唔出）。

可拆模組（🧩）：
    permission.py   等確認——aider confirm_ask 轉 ACP request_permission 卡。
                    缺席＝自動模式（AIDER_ACP_AUTO_YES——默認拒絕）。
                    下面嘅 try/except 係「可拆模組」邊界（唔係防呆）：
                    刪咗 permission.py ＝ 自動回落，零代碼改動、零殘線。
    litellm_io.py   tool 訊息分流——_build_io 內 try-import；缺席＝回落原版 InputOutput。
"""

import asyncio
import copy
import os
import sys
import threading
from io import StringIO
from uuid import uuid4

from acp import PROTOCOL_VERSION
from acp.schema import (
    AgentCapabilities,
    AgentMessageChunk,
    AgentThoughtChunk,
    AllowedOutcome,
    Cost,
    Implementation,
    InitializeResponse,
    NewSessionResponse,
    PermissionOption,
    PromptResponse,
    TextContentBlock,
    ToolCallUpdate,
    UsageUpdate,
)

from ..runtime import sc_logger

# 🧩 可拆模組：permission（等確認——刪咗 permission.py 即完成移除，見檔頭）
try:
    from . import permission as permission_module
except ImportError:
    permission_module = None


class acp_agent_class:
    """ACP agent（singleton——一個程序一個實例；run_agent 全程用佢）。"""

    _instance: "acp_agent_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "acp_agent_class":
        """取得 acp_agent_class 單例（整個程序共用同一實例）。"""
        return cls()

    def __init__(self) -> None:
        if not hasattr(self, "_sessions"):
            # session_id → {session_id, cwd, coder, cancel_event}（v0：程序內存——冇持久化）
            self._sessions: dict = {}
            self.conn = None
            self._loop = None

    # ----------------------------------------------------------
    # 連線回呼（AgentSideConnection 建立時呼叫——SDK 慣例）
    # ----------------------------------------------------------
    def on_connect(self, conn) -> None:
        """收連線（出 session/update／request_permission 嘅唯一通道）＋鎖定 event loop。"""
        self.conn = conn
        self._loop = asyncio.get_running_loop()
        sc_logger.log("client connected")

    # ----------------------------------------------------------
    # 協議核心：三件＋取消（handler 收 request 欄位做 kwargs——SDK 慣例）
    # ----------------------------------------------------------
    async def initialize(self, protocol_version=None, client_capabilities=None, **kwargs):
        """握手：報協議版本＋能力（v0：冇 load_session——client 唔會試續話）。"""
        sc_logger.log(f"initialize: client protocol_version={protocol_version}")
        return InitializeResponse(
            protocol_version=PROTOCOL_VERSION,
            agent_capabilities=AgentCapabilities(load_session=False),
            agent_info=Implementation(name="hkai-aider-acp", title="aider (ACP) — hkai", version="0.1.0"),
        )

    async def new_session(self, cwd=None, history=None, system_instructions=None, **kwargs):
        """開對話：記 cwd＋歷史＋系統指示（client 隨 session/new meta 帶入——每輪重建；coder 首次 prompt 才建——零失敗面）。"""
        session_id = uuid4().hex
        self._sessions[session_id] = {
            "session_id": session_id,
            "cwd": cwd or os.getcwd(),
            "history": history or [],
            "system_instructions": system_instructions or {},
            "coder": None,
            "cancel_event": threading.Event(),
        }
        sc_logger.log(
            f"session new: {session_id} (cwd={self._sessions[session_id]['cwd']}, "
            f"history={len(self._sessions[session_id]['history'])} msgs, "
            f"system_prompt={len(self._sessions[session_id]['system_instructions'].get('system_prompt_text', ''))} chars, "
            f"read_only={len(self._sessions[session_id]['system_instructions'].get('read_only_paths', []))} files)"
        )
        return NewSessionResponse(session_id=session_id)

    async def cancel(self, session_id=None, **kwargs) -> None:
        """取消：落旗標——aider 迴圈喺下一個分段邊界收手（協定通知——冇回覆）。"""
        session = self._sessions.get(session_id)

        # 判定：未知 session → 冇嘢可取消
        if session is None:
            return None

        session["cancel_event"].set()
        sc_logger.log(f"cancel requested: {session_id}")
        return None

    async def prompt(self, session_id=None, prompt=None, **kwargs):
        """一輪提問：文字抽出 → worker thread 跑 aider → 串流更新 → 回 stop_reason。"""
        session = self._sessions.get(session_id)

        # 未知 session＝呼叫次序錯（協議入口——直接爆，唔兜）
        if session is None:
            raise ValueError("unknown session: " + str(session_id))

        user_text = self._extract_prompt_text(prompt)
        session["cancel_event"].clear()
        sc_logger.log(f"prompt: {session_id} ({len(user_text)} chars)")

        stop_reason = await asyncio.to_thread(self._run_turn, session_id, user_text)
        return PromptResponse(stop_reason=stop_reason)

    # ----------------------------------------------------------
    # 內部（prompt 流程）
    # ----------------------------------------------------------
    @staticmethod
    def _extract_prompt_text(prompt_blocks) -> str:
        """ACP prompt 塊 → 純文字（v0：只收文字塊——其餘（圖／資源）略過）。"""
        text_pieces = []

        for prompt_block in (prompt_blocks or []):
            # 判定：文字塊 → 收
            if isinstance(prompt_block, TextContentBlock):
                text_pieces.append(prompt_block.text or "")

        return "".join(text_pieces)

    def _run_turn(self, session_id, user_text):
        """worker thread：跑 aider 一輪——逐段文字交返 loop（回 stop_reason）。"""
        session = self._sessions[session_id]
        message_id = uuid4().hex[:12]

        try:
            coder = session["coder"]

            # 判定：首次 prompt → 建 coder（其後重用——對話記憶自此累積喺記憶體）
            if coder is None:
                coder = self._build_coder(session)
                session["coder"] = coder

            # 分段狀態（思考段開／收——跨段記住；見 _emit_piece）
            reasoning_state = {"opened": False, "closed": False}

            for text_piece in coder.run_stream(user_text):
                # 判定：已取消 → 收手（下一段邊界）
                if session["cancel_event"].is_set():
                    sc_logger.log(f"turn cancelled: {session_id}")
                    return "cancelled"

                # 判定：空段 → 唔出
                if not text_piece:
                    continue

                self._emit_piece(session_id, message_id, text_piece, reasoning_state)

            # 本輪用量（aider 統計——標準用量事件；沒有數字＝唔出）
            self._send_usage_update(session_id, coder)

        except BaseException as turn_error:
            sc_logger.log(f"turn failed: {session_id} :: {turn_error!r}")
            raise

        return "end_turn"

    def _send_text_update(self, session_id, message_id, text_piece) -> None:
        """worker thread → loop：出 session/update（agent_message_chunk——同一 message_id 串一段）。"""
        update = AgentMessageChunk(
            content=TextContentBlock(type="text", text=text_piece),
            message_id=message_id,
            session_update="agent_message_chunk",
        )
        update_future = asyncio.run_coroutine_threadsafe(
            self.conn.session_update(session_id=session_id, update=update),
            self._loop,
        )
        update_future.result()

    def _send_thought_update(self, session_id, message_id, text_piece) -> None:
        """worker thread → loop：出 session/update（agent_thought_chunk——思考段）。"""
        update = AgentThoughtChunk(
            content=TextContentBlock(type="text", text=text_piece),
            message_id=message_id,
            session_update="agent_thought_chunk",
        )
        update_future = asyncio.run_coroutine_threadsafe(
            self.conn.session_update(session_id=session_id, update=update),
            self._loop,
        )
        update_future.result()

    def _emit_piece(self, session_id, message_id, text_piece, reasoning_state) -> None:
        """段翻譯：aider 文字段 → 按思考標記拆兩頻道（thought／text——見檔頭）。"""
        reasoning_markers = self._sessions[session_id]["reasoning_markers"]
        reasoning_start_marker = reasoning_markers["start"]
        reasoning_end_marker = reasoning_markers["end"]

        remaining_text = text_piece

        # 判定：思考段未開、段內有開標記 → 開思考段（標記之前殘字＝主回答段）
        if reasoning_state["opened"] is False and reasoning_start_marker in remaining_text:
            text_before_open, remaining_text = remaining_text.split(reasoning_start_marker, 1)

            # 判定：標記前有實字（正常＝空／換行）→ 當主回答段
            if text_before_open.strip() != "":
                self._send_text_update(session_id, message_id, text_before_open)

            remaining_text = remaining_text.lstrip("\n")
            reasoning_state["opened"] = True

        # 判定：思考段開咗未收、段內有收標記 → 收思考段（其後＝主回答段）
        if reasoning_state["opened"] is True and reasoning_state["closed"] is False and reasoning_end_marker in remaining_text:
            reasoning_text, remaining_text = remaining_text.split(reasoning_end_marker, 1)
            reasoning_text = reasoning_text.rstrip("\n")

            # 判定：思考段有字 → 出思考頻道
            if reasoning_text != "":
                self._send_thought_update(session_id, message_id, reasoning_text)

            remaining_text = remaining_text.lstrip("\n")
            reasoning_state["closed"] = True

        # 判定：段已消化完 → 收工
        if remaining_text == "":
            return

        # 判定：思考段進行中（開咗未收）→ 思考頻道
        if reasoning_state["opened"] is True and reasoning_state["closed"] is False:
            self._send_thought_update(session_id, message_id, remaining_text)
            return

        # 其餘（未開／已收）→ 主回答頻道
        self._send_text_update(session_id, message_id, remaining_text)

    def _send_usage_update(self, session_id, coder) -> None:
        """worker thread → loop：出用量更新（UsageUpdate——標準用量事件；aider 統計）。"""
        # 本輪統計（折疊前後皆安全：aider 只整批搬 message→total 再清零——兩者相加唔重複）
        tokens_sent = int(getattr(coder, "total_tokens_sent", 0) or 0) + int(getattr(coder, "message_tokens_sent", 0) or 0)
        tokens_received = int(getattr(coder, "total_tokens_received", 0) or 0) + int(getattr(coder, "message_tokens_received", 0) or 0)
        context_window = int(getattr(coder.main_model, "max_input_tokens", 0) or 0)
        session_cost = float(getattr(coder, "total_cost", 0.0) or 0.0) + float(getattr(coder, "message_cost", 0.0) or 0.0)

        # 判定：完全冇數字（空回）→ 唔出
        if tokens_sent == 0 and tokens_received == 0:
            return

        # 判定：有成本 → 附金額（USD）
        cost = None
        if session_cost > 0:
            cost = Cost(amount=session_cost, currency="USD")

        update = UsageUpdate(
            used=tokens_sent + tokens_received,
            size=context_window,
            cost=cost,
            session_update="usage_update",
        )
        update_future = asyncio.run_coroutine_threadsafe(
            self.conn.session_update(session_id=session_id, update=update),
            self._loop,
        )
        update_future.result()

    def confirm_via_client(self, session_id, question, subject):
        """worker thread → client：出權限卡（request_permission）——等答，回 bool。

        時限：協定慣例＝「等嘅一邊唔設死線、答嘅一邊（client）自己計」（同 goose 側一致）。
        """
        title = question if not subject else question + "（" + subject + "）"

        # 原始參數（權限卡 args 顯示源——aider confirm 唯一結構化資料＝subject）
        tool_call = ToolCallUpdate(
            tool_call_id=uuid4().hex[:12],
            title=title,
            raw_input={"subject": subject} if subject else None,
        )
        options = [
            PermissionOption(option_id="allow_once", name="Allow", kind="allow_once"),
            PermissionOption(option_id="reject_once", name="Deny", kind="reject_once"),
        ]

        answer_future = asyncio.run_coroutine_threadsafe(
            self.conn.request_permission(session_id=session_id, tool_call=tool_call, options=options),
            self._loop,
        )
        permission_response = answer_future.result()

        # 判定：client 揀咗（AllowedOutcome）→ 睇揀咗邊個 option
        if isinstance(permission_response.outcome, AllowedOutcome):
            return permission_response.outcome.option_id == "allow_once"

        # 其餘（DeniedOutcome——client 面收線等）→ 唔准
        return False

    # ----------------------------------------------------------
    # 內部（建 coder——首次 prompt）
    # ----------------------------------------------------------
    def _build_coder(self, session):
        """建 aider Coder（config 先載→env 就緒→先 import aider；工作目錄＝session cwd）。"""
        from .context import config

        # env 先就緒（config 會載 env 檔——aider／litellm 讀 env）
        config_data = config()

        model_name = config_data["model_name"]

        # 判定：冇指定模型 → 冇嘢跑得（啟動要求——直接爆）
        if not model_name:
            raise RuntimeError("AIDER_ACP_MODEL 未設——唔知用邊個模型")

        from aider.coders import Coder
        from aider.models import Model
        from aider.reasoning_tags import REASONING_END, REASONING_START

        # 工作目錄＝session cwd（v0：一個程序一個 cwd——多 cwd session 未支援）
        os.chdir(session["cwd"])

        io_instance = self._build_io(session)

        # 系統指示材料（client 帶入——system_prompt 全文＋唯讀檔列）
        system_instructions = session["system_instructions"]
        system_prompt_text  = str(system_instructions.get("system_prompt_text", ""))
        read_only_paths     = list(system_instructions.get("read_only_paths", []))

        model = Model(model_name)
        coder = Coder.create(
            main_model=model,
            fnames=[],
            read_only_fnames=read_only_paths,
            io=io_instance,
            restore_chat_history=False,
            edit_format=config_data["edit_format"] or None,
        )

        # 系統提示附加（hkai system_prompt——接喺 aider 主系統提示尾端）
        # 大括號先加倍轉義：系統提示每輪經 .format()（模板佔位）——唔轉義＝KeyError
        # （改嘅係實例副本——類級 gpt_prompts 唔郁）
        if system_prompt_text != "":
            gpt_prompts_copy = copy.copy(coder.gpt_prompts)
            gpt_prompts_copy.main_system = gpt_prompts_copy.main_system + "\n\n" + system_prompt_text.replace("{", "{{").replace("}", "}}")
            coder.gpt_prompts = gpt_prompts_copy

        # 歷史注入（client 帶入——done_messages；restore_chat_history=False＝唔讀引擎自身歷史）
        done_messages = []

        # 逐條歷史 → aider 訊息形（{role, content}——aider done_messages 注入形）
        for history_record in session["history"]:
            done_messages.append({
                "role":    history_record["role"],
                "content": history_record["content"],
            })

        coder.done_messages = done_messages

        # 觸發 aider 內建歷史摘要（還原路徑被跳過——唔補嘅話歷史永不摘要；細歷史＝零成本）
        coder.summarize_start()

        # 思考段標記（向 aider 讀實際值——串流拆段用；唔硬編）
        session["reasoning_markers"] = {"start": REASONING_START, "end": REASONING_END}

        sc_logger.log(
            f"coder ready: {session['session_id']} (model={model_name}, history={len(done_messages)} msgs, "
            f"system_prompt={len(system_prompt_text)} chars, read_only={len(read_only_paths)} files)"
        )
        return coder

    def _build_io(self, session):
        """建 InputOutput——tool 分流（litellm_io）＋permission 模組在＝出卡等確認；缺席＝自動模式。"""
        # 判定：permission 模組在 → 交佢建（confirm_ask 轉 ACP 卡）
        if permission_module is not None:
            return permission_module.build_io(
                agent_ref=self,
                session_id=session["session_id"],
            )

        # 自動模式（permission 模組缺席——yes 由 AIDER_ACP_AUTO_YES 話事）
        from .context import config

        # 🧩 可拆模組：litellm_io（tool 訊息分流——缺席＝回落原版 InputOutput）
        try:
            from .litellm_io import litellm_io_class as _io_base_class
        except ImportError:
            from aider.io import InputOutput as _io_base_class

        return _io_base_class(
            yes=config()["auto_yes"],
            pretty=False,
            encoding="utf-8",
            input=StringIO(""),
            output=sys.stderr,
            fancy_input=False,
        )

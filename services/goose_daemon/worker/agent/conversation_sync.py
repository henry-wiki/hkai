#!/usr/bin/env python3
"""
conversation_sync.py

goose session 同步（發問前檢查——entry.execute_job 呼叫）。

機制（checkpoints 快照——每對話一檔）：
    檔：<storage>/localdb/checkpoints/<project_slug>/<chat_sid>.json
    形：{"curr": {"stats": {"message_count": N, "max_message_id": M}, "checksum": md5}, "prev": {...}}

每次發問前（問題發去 goose 之前）：
    1. 讀快照——比對「今次問題之前」現場統計（messages 表 count／max message_id——唔數答案行）。
    2. 唔等（或檔案唔存在／壞）＝有修改 → 用我方對話記錄重寫該對話 goose session 的訊息
       （session 由 goose 自己建；未建＝本輪跳過；已建＝原地重寫 messages）。
    3. 寫新快照（curr→prev 移位；curr＝含今次問題嘅統計）。

對齊口徑（防「自己嗰輪」干擾——send 時問題行已入庫、答案後至）：
    比對＝message_id < 今次；快照＝message_id <= 今次——答案行唔入統計，兩邊自然對齊。

換 model／effort：官方 ACP session/set_config_option（acp_client._apply_config_options——provider／model／thinking_effort）；
    唔再改 goose DB 記錄（session 歷史原樣保留）。

開關：無（常開）——checkpoints 根資料夾（localdb/checkpoints）唔存在＝當場自動建立；建立失敗＝上層盲捕記 log（fail-open，唔阻斷訊息）。
異常：一律盲捕記 log（fail-open——唔阻斷訊息；有修改但同步失敗＝唔前進快照，下輪再試）。
"""

import json
import os
import subprocess
import sys
import time
import uuid

from ...context import chat_db, goose_db, message_store, timer
from bootstrap import sc_logger
from . import checkpoint, paths


class conversation_sync_class:
    """
    session 同步（singleton——整個程序只有一個實例）。

    職責單一：發問前令 goose session 對齊我哋嘅真相——model 對齊（換咗＝更新記錄＋重建）＋快照比對（有變即全量重建）；不含 goose 執行。
    """

    _instance: "conversation_sync_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "conversation_sync_class":
        """取得 conversation_sync_class 單例。"""
        return cls()

    # ----------------------------------------------------------
    # 主流程
    # ----------------------------------------------------------

    def reconcile_history(self) -> None:
        """發問前同步對話歷史（確保資料夾 → chat_sid → 讀快照 → 對比 →（有變）重建 → 寫快照）。"""
        # 語境（msg_conf——execute_job 已載入）
        chat_id         = message_store().get_chat_id()
        chat_sid        = message_store().get_chat_sid()
        message_id      = message_store().get_message_id()
        project_path    = message_store().get_project_path()
        project_slug    = message_store().get_project_slug()

        checkpoints_base_dir = paths.resolve_checkpoints_dir()

        # 判定：checkpoints 根唔存在 → 當場自動建立（功能常開；建唔到＝上層盲捕記 log，唔阻斷訊息）
        if checkpoints_base_dir.is_dir() is False:
            checkpoints_base_dir.mkdir(parents=True, exist_ok=True)

        # 判定：冇 chat_sid（msg_conf 未帶——PHP 正常必寫）→ 當錯誤（工單失敗、工人退出）
        if chat_sid == "":
            raise RuntimeError("chat_sid missing (chat_id=" + chat_id + ")")

        # 現場統計（唔含今次問題＝對比用；含今次問題＝寫快照用）
        live_stats_before   = chat_db().query_live_stats(chat_id, message_id, False)
        live_stats_upto     = chat_db().query_live_stats(chat_id, message_id, True)

        # 讀快照（缺失／損壞／checksum 唔對＝None）
        stored_curr_stats = checkpoint.read(project_slug, chat_sid)

        # 判定：快照唔存在（首次／新對話）→ 必有修改
        if stored_curr_stats is None:
            is_modified = True
        # 判定：快照在 → 對比現場統計（唔等＝有修改）
        else:
            is_modified = (stored_curr_stats != live_stats_before)

        # 判定：有修改 → 全量重建（用我方對話——今次問題以外）
        if is_modified is True:
            sc_logger.log("conversation_sync: change detected (chat_id=" + chat_id + ") - rebuilding session")
            self._rebuild_session_from_conversation(chat_id, message_id, project_path)

        # 寫新快照（curr→prev 移位；curr＝含今次問題嘅現場統計）
        checkpoint.save(project_slug, chat_sid, live_stats_upto, stored_curr_stats)


    # ----------------------------------------------------------
    # 查詢（我方 DB）
    # ----------------------------------------------------------

    # ----------------------------------------------------------
    # 全量重建（goose sessions.db）
    # ----------------------------------------------------------

    def _rebuild_session_from_conversation(self, chat_id: str, message_id: str, project_path: str) -> None:
        """全量重寫該對話 session 的訊息（用我方對話——今次問題以外；goose 未建 session＝跳過，唔代佢建）。"""
        self._ensure_sessions_db(project_path)

        session_name = "hkai-" + message_store().get_chat_sid()
        goose_session_db = goose_db(project_path)
        session_id = goose_session_db.find_session_id(session_name)

        # 判定：goose 未建立該對話 session（首次）→ 唔重建（交返 goose 自己開；下一輪有變才同步）
        if session_id == "":
            sc_logger.log("conversation_sync: rebuild skipped (no goose session=" + session_name + ")")
            return

        conversation_rows = self._build_conversation_rows(chat_id, message_id, session_id)
        row_count = goose_session_db.replace_session_messages(session_id, conversation_rows)

        sc_logger.log("conversation_sync: session rebuilt (name=" + session_name + ", rows=" + str(row_count) + ")")

    def _ensure_sessions_db(self, project_path: str) -> None:
        """sessions.db 唔存在（此專案從未行過 goose）→ 官方 CLI 開一次（goose 自建 DB＋schema）。"""
        sessions_db_file = paths.resolve_sessions_db_file(project_path)

        # 判定：DB 已存在 → 直返
        if sessions_db_file.is_file() is True:
            return

        goose_executable = paths.resolve_executable()

        # 判定：執行檔解析唔到 → 拋返上層（盲捕記 log——快照唔前進，下輪再試）
        if goose_executable == "":
            raise RuntimeError("goose executable not found (session db init)")

        creationflags = 0

        # 判定：Windows → 無視窗
        if sys.platform == "win32":
            creationflags = subprocess.CREATE_NO_WINDOW

        goose_env = dict(os.environ)
        goose_env["GOOSE_PATH_ROOT"] = str(paths.resolve_path_root(project_path))


        # session list（banner 建立 DB＋schema——輸出唔理）
        _ = subprocess.run(
            [goose_executable, "session", "list"],
            env=goose_env,
            stdin=subprocess.DEVNULL,
            capture_output=True,
            text=True,
            check=False,
            encoding="utf-8",
            errors="replace",
            timeout=60,
            creationflags=creationflags,
        )


    def _build_conversation_rows(self, chat_id: str, message_id: str, session_id: str) -> list[tuple[object, ...]]:
        """我方對話 → goose 訊息列（user／assistant 文字對——今次問題以外，按序）。"""
        our_message_rows, answer_text_by_message = chat_db().read_exchange_source(chat_id, message_id)
        tool_text_by_message = chat_db().read_tool_call_summaries(chat_id, message_id)

        conversation_rows: list[tuple[object, ...]] = []
        last_created_timestamp = 0

        for our_message_row in our_message_rows:
            our_message_id = int(str(our_message_row[0]))
            our_message_text = str(our_message_row[1] or "")
            our_created_at = str(our_message_row[2] or "")

            try:
                created_timestamp = timer().to_epoch_seconds(our_created_at)
            except ValueError:
                # 解析唔到 → 用當前時間（排序由遞增補回）
                created_timestamp = int(time.time())

            # 判定：時間戳唔前進（同秒多列）→ +1（保嚴格遞增排序）
            if created_timestamp <= last_created_timestamp:
                created_timestamp = last_created_timestamp + 1
            last_created_timestamp = created_timestamp

            conversation_rows.append(self._build_message_row("user", our_message_text, created_timestamp, session_id))

            answer_text = answer_text_by_message.get(our_message_id, "")

            # 判定：該訊息有工具調用記錄 → 附喺答案後面（agent 恢復後仍知做過咩命令）
            tool_lines = tool_text_by_message.get(our_message_id, [])

            if len(tool_lines) > 0:
                answer_text = answer_text + chr(10) + chr(10) + "[執行過嘅命令]" + chr(10) + chr(10).join("- " + line for line in tool_lines)

            # 判定：有答案文字 → 補 assistant 列（無＝略——例如取消咗嗰啲）
            if answer_text != "":
                last_created_timestamp = last_created_timestamp + 1
                conversation_rows.append(self._build_message_row("assistant", answer_text, last_created_timestamp, session_id))

        return conversation_rows

    def _build_message_row(self, role: str, text: str, created_timestamp: int, session_id: str) -> tuple[object, ...]:
        """組一列 goose 訊息（content_json／metadata 格式同 goose 自家一致）。"""
        content_json = json.dumps(
            [{"type": "text", "text": text}],
            ensure_ascii=False,
            separators=(",", ":"),
        )
        metadata_json = json.dumps(
            {"userVisible": True, "agentVisible": True},
            separators=(",", ":"),
        )
        return ("msg_" + str(uuid.uuid4()), session_id, role, content_json, created_timestamp, metadata_json)

    # ----------------------------------------------------------

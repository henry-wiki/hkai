#!/usr/bin/env python3
"""
agent_history.py

agent 對話歷史（agent_history_class singleton——get_instance() 取得）。

職責單一：由我方 DB 讀對話交流 → 組 agent 歷史（[{role, content}]——今次問題以外，按序）。
供無 session 持久化引擎（aider_daemon.acp）隨 session/new 帶入——每輪重建，單一 ledger
（我哋 DB 永遠係真相，唔靠引擎自身 session 持久化）。

來源：messages＋message_response（response_result 兩查併文；failed 訊息唔入——無法送出卡＝錯誤源）。
"""

import sqlite3
from pathlib import Path

from ..context import *


class agent_history_class:
    """
    agent 歷史組裝（singleton——整個程序只有一個實例）。

    職責單一：讀我方對話來源、組 [{role, content}]；不含引擎執行／傳送。
    """

    _instance: "agent_history_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "agent_history_class":
        """取得 agent_history_class 單例。"""
        return cls()

    def build_agent_history(self, chat_id: str, message_id: int) -> list:
        """
        我方對話 → agent 歷史（[{role, content}]——今次問題以外，按序；每輪重建——單一 ledger）。
        """
        our_message_rows, answer_text_by_message = self._read_exchange_source(chat_id, message_id)

        history_messages = []

        # 逐條我方訊息 → 組 user／assistant 則（有答案＝補 assistant）
        for our_message_row in our_message_rows:
            our_message_id = int(our_message_row[0])
            our_message_text = str(our_message_row[1] or "")

            history_messages.append({"role": "user", "content": our_message_text})

            answer_text = answer_text_by_message.get(our_message_id, "")

            # 判定：有答案文字 → 補 assistant 則（無＝略——例如取消咗嗰啲）
            if answer_text != "":
                history_messages.append({"role": "assistant", "content": answer_text})

        return history_messages

    def _read_exchange_source(self, chat_id: str, message_id: int) -> tuple:
        """讀我方對話交流（messages＋response_result 兩查＋答案併文）。"""
        our_db_file = self._resolve_our_db_file()
        connection = sqlite3.connect("file:" + our_db_file.as_posix() + "?mode=ro", uri=True)
        try:
            # 篩選：只取真交流——failed 訊息（無法送出卡＝錯誤源）唔入歷史
            our_message_rows = connection.execute(
                "SELECT message_id, message_content, message_created_at FROM messages WHERE chat_id = ? AND message_id < ? AND message_status != 'failed' ORDER BY message_id ASC",
                (int(chat_id), int(message_id)),
            ).fetchall()

            answer_rows = connection.execute(
                "SELECT message_id, response_index, response_content FROM message_response WHERE chat_id = ? AND message_id < ? AND response_type = 'response_result' ORDER BY message_id ASC, response_index ASC",
                (int(chat_id), int(message_id)),
            ).fetchall()
        finally:
            connection.close()

        # 答案併文（同一訊息多段——按 response_index 次序貼埋）
        answer_text_by_message = {}
        for answer_row in answer_rows:
            answer_message_id = int(answer_row[0])
            answer_text_by_message[answer_message_id] = answer_text_by_message.get(answer_message_id, "") + str(answer_row[2] or "")

        return our_message_rows, answer_text_by_message

    def _resolve_storage_dir(self) -> str:
        """storage 根（runtime.json storage_dir）。"""
        return str(daemon().get_daemon_conf_data().get("storage_dir", ""))

    def _resolve_our_db_file(self) -> Path:
        """我方 SQLite（hkai_chat_data.sqlite——歷史唯一真相源；剝 Windows 裝置前綴——sqlite URI 前置）。"""
        storage_path_text = str(self._resolve_storage_dir()).replace("\\", "/")
        # 判定：帶 Windows 裝置前綴（"//./"）→ 剝走（sqlite URI 解析會誤當 authority）
        if storage_path_text.startswith("//./"):
            storage_path_text = storage_path_text[4:]
        # 判定：帶 Windows 長路徑前綴（"//?/"）→ 剝走（同上）
        if storage_path_text.startswith("//?/"):
            storage_path_text = storage_path_text[4:]
        return Path(storage_path_text) / "localdb" / "hkai_chat_data.sqlite"

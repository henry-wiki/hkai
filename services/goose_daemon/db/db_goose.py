#!/usr/bin/env python3
"""
db_goose.py

goose 自己的 SQLite（`<GOOSE_PATH_ROOT>/data/sessions/sessions.db`）——讀寫集中此檔。

上游：conversation_sync（找 session／重寫 messages）
下游：db_connection_class（連線政策）＋ db_context（SQL 契約）
注意：goose 私有 schema（sessions／messages）——goose 升級可能變，改動只改此檔。
"""

from ..worker.engine import engine_context
from . import db_context
from .db_connection import db_connection_class


class goose_db_class(db_connection_class):
    """goose session DB 存取（每專案一個檔——由 bridging 按 project_path 建立）。"""

    def __init__(self, project_path: str):
        """注入該專案 sessions DB 路徑（讀寫）。"""
        super().__init__(engine_context.resolve_sessions_db_file(project_path), read_only=False)

    def find_session_id(self, session_name: str) -> str:
        """取該對話現存 goose session id（同名最新；冇＝空字串——建立責任交返 goose）。"""
        session_row = self.query_one(db_context.SQL_FIND_SESSION_BY_NAME, (session_name,))

        # 判定：冇同名 session → 空字串
        if session_row is None:
            return ""

        return str(session_row[0])

    def replace_session_messages(self, session_id: str, message_rows: list) -> int:
        """原地重寫該 session 全部訊息（單一交易 DELETE ＋ INSERT；回傳寫入筆數）。"""
        with self.transaction() as connection:
            connection.execute(db_context.SQL_DELETE_SESSION_MESSAGES, (session_id,))
            connection.executemany(db_context.SQL_INSERT_SESSION_MESSAGE, message_rows)

        return len(message_rows)

    def sessions_db_exists(self) -> bool:
        """該專案 goose sessions DB 是否存在（新專案首次＝False）。"""
        return self._db_file.is_file()

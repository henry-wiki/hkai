#!/usr/bin/env python3
"""
db_hkai.py

我們自己的 SQLite（`hkai_chat_data.sqlite`）唯讀查詢——功能模組唔直接寫 SQL。

上游：conversation_sync（快照比對／重建對話）
下游：db_connection_class（連線政策）＋ db_context（SQL 契約）
"""

from ..worker.agent import paths
from . import db_context
from .db_connection import db_connection_class


class chat_db_class(db_connection_class):
    """我們 DB 唯讀存取（singleton——無狀態，只作統一入口）。"""

    _instance: "chat_db_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        # 判定：未有實例 → 建立
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    def __init__(self):
        """注入我們 DB 路徑（唯讀）。"""
        super().__init__(paths.resolve_chat_db_file(), read_only=True)

    @classmethod
    def get_instance(cls):
        """取得單例（bridging 用）。"""
        return cls()

    def query_live_stats(self, chat_id: str, message_id: str, include_current: bool) -> dict[str, int]:
        """現場統計（messages 筆數＋最大 message_id）；DB 唔存在＝全 0。"""
        # 界線：含今次問題用 <=；唔含用 <（send 時問題行已入庫、答案後至）
        if include_current is True:
            stats_sql = db_context.SQL_LIVE_STATS_INCLUDE_CURRENT
        else:
            stats_sql = db_context.SQL_LIVE_STATS_EXCLUDE_CURRENT

        stats_row = self.query_one(stats_sql, (int(chat_id), int(message_id)))

        # 判定：冇 DB → 空統計
        if stats_row is None:
            return {"message_count": 0, "max_message_id": 0}

        return {
            "message_count": int(str(stats_row[0])),
            "max_message_id": int(str(stats_row[1])),
        }

    def read_exchange_source(self, chat_id: str, message_id: str) -> tuple[list[tuple[object, ...]], dict[int, str]]:
        """讀我方對話交流（messages＋message_response 兩查＋答案併文）；DB 唔存在＝空。"""
        message_rows = self.query_all(
            db_context.SQL_READ_MESSAGES,
            (int(chat_id), int(message_id)),
        )

        answer_rows = self.query_all(
            db_context.SQL_READ_ANSWERS,
            (int(chat_id), int(message_id)),
        )

        # 答案併文（同一訊息多段——按 response_index 次序貼埋）
        answer_text_by_message: dict[int, str] = {}
        for answer_row in answer_rows:
            answer_message_id = int(str(answer_row[0]))
            answer_text = str(answer_row[2] or "")
            answer_text_by_message[answer_message_id] = answer_text_by_message.get(answer_message_id, "") + answer_text

        return message_rows, answer_text_by_message

    def read_tool_call_summaries(self, chat_id: str, message_id: str) -> dict[int, list[str]]:
        """讀工具調用摘要（message_tool_call——按訊息／輪次／次序）；DB 冇表或冇資料＝空。"""
        tool_text_by_message: dict[int, list[str]] = {}

        try:
            tool_rows = self.query_all(
                "SELECT message_id, tool_name, input_text, status FROM message_tool_call"
                " WHERE chat_id = ? AND message_id < ? ORDER BY message_id, response_index, seq",
                (int(chat_id), int(message_id)),
            )
        except Exception:
            return tool_text_by_message

        for tool_row in tool_rows:
            tool_message_id = int(str(tool_row[0]))
            tool_name = str(tool_row[1] or "")
            tool_input_full = " ".join(str(tool_row[2] or "").split())
            tool_input = tool_input_full

            # 判定：超長 → 截短＋省略號（一眼睇出仲有內容）
            if len(tool_input_full) > 200:
                tool_input = tool_input_full[:200] + "..."
            tool_status = str(tool_row[3] or "")

            tool_line = tool_name + ": " + tool_input

            # 判定：有狀態 → 附係尾
            if tool_status != "":
                tool_line = tool_line + " [" + tool_status + "]"

            if tool_message_id not in tool_text_by_message:
                tool_text_by_message[tool_message_id] = []

            tool_text_by_message[tool_message_id].append(tool_line)

        return tool_text_by_message

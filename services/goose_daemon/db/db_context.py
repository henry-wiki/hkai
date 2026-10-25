#!/usr/bin/env python3
"""
db_context.py

資料庫契約（單一來源）——所有 DB 檔名／SQL 語句集中此檔；`chat_db`／`goose_db` 只查表使用。

界線：
    我們 DB（hkai_chat_data.sqlite）→ CHAT_* ／ SQL_*（唯讀查詢）
    goose DB（sessions.db）         → SESSIONS_* ／ SQL_*（session／messages）
改 schema 或 SQL＝只改此檔（呼叫端簽名不變）。
"""

# 我們 DB 檔名（位於 storage/localdb/）
CHAT_DB_FILENAME = "hkai_chat_data.sqlite"

# goose session DB 檔名（位於 GOOSE_PATH_ROOT/data/sessions/）
SESSIONS_DB_FILENAME = "sessions.db"

# 現場統計（messages 筆數＋最大 message_id；含／唔含今次問題兩種口徑）
SQL_LIVE_STATS_INCLUDE_CURRENT = (
    "SELECT COUNT(*), COALESCE(MAX(message_id), 0) FROM messages "
    "WHERE chat_id = ? AND message_id <= ?"
)
SQL_LIVE_STATS_EXCLUDE_CURRENT = (
    "SELECT COUNT(*), COALESCE(MAX(message_id), 0) FROM messages "
    "WHERE chat_id = ? AND message_id < ?"
)

# 對話來源（我們 DB——重建 goose session 用）
SQL_READ_MESSAGES = (
    "SELECT message_id, message_content, message_created_at FROM messages "
    "WHERE chat_id = ? AND message_id < ? AND message_status != 'failed' "
    "ORDER BY message_id ASC"
)
SQL_READ_ANSWERS = (
    "SELECT message_id, response_index, response_content FROM message_response "
    "WHERE chat_id = ? AND message_id < ? AND response_type = 'response_result' "
    "ORDER BY message_id ASC, response_index ASC"
)

# goose session（同名取最新；建立責任交返 goose）
SQL_FIND_SESSION_BY_NAME = (
    "SELECT id FROM sessions WHERE name = ? ORDER BY created_at DESC LIMIT 1"
)

# goose session 訊息（原地重寫：先清後插）
SQL_DELETE_SESSION_MESSAGES = "DELETE FROM messages WHERE session_id = ?"
SQL_INSERT_SESSION_MESSAGE = (
    "INSERT INTO messages (message_id, session_id, role, content_json, created_timestamp, metadata_json) "
    "VALUES (?, ?, ?, ?, ?, ?)"
)

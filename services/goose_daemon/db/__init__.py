#!/usr/bin/env python3
"""
db 套件

集中所有資料庫讀寫（我們 DB ／ goose DB）——功能模組唔直接掂 SQL。

界線：
    我們 DB（hkai_chat_data.sqlite）→ db_hkai.py（唯讀）
    goose DB（sessions.db）         → db_goose.py（讀＋寫 messages）
"""

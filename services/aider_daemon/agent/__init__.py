#!/usr/bin/env python3
"""
agent/——aider agent接駁層（本 daemon 專用——對位 goose_daemon agent/）。

    agent_history.py   agent_history_class(singleton)——我方 DB 對話 → agent 歷史（隨 session/new 帶入）
    aider_entry.py     aider_entry_class(singleton)——執行入口（冷／暖；spawn aider_daemon.acp 子程序）
    acp_client.py      acp_client(singleton)——ACP 協定客戶端（stdio 驅動——拉 aider_daemon.acp）
"""

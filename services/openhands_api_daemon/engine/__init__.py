#!/usr/bin/env python3
"""
engine/——引擎接駁層（本 daemon 專用——對位 aider_daemon engine/）。

    auth_setup.py      api_key_loader_class(singleton)——API keys 載入環境變數（引擎子程序由此取 key）
    openhands_api_entry.py     openhands_api_entry_class(singleton)——執行入口（冷／暖；stream-json 翻譯）
"""

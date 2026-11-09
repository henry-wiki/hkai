#!/usr/bin/env python3
"""
load.py — 運行前唯一入口（對應 web/sc_config/load.php）。

職責：按固定次序把 bootstrap 三件串成一次呼叫——任何程序只需 load() 一次，環境／資料根／設定全部就位。
- load()：env.init_env()（讀 .env）→ get_data_dir()（解析資料根）→ config.init_config()（讀 runtime.json）

次序不可調換：config 要靠資料根先定位 runtime.json。
重複呼叫安全（idempotent）——每次都重讀，值為最新。

上游：bridge main（server.initialize）、goose_daemon daemon_manager.load_config
下游：bootstrap/env.py、bootstrap/path_resolve/、bootstrap/config.py
"""

from . import config, env
from .path_resolve.get_data_dir import get_data_dir


def load() -> None:
    """載入 .env → 解析資料根 → 讀 runtime.json（次序固定；可重複呼叫）。"""
    _ = env.init_env()

    _ = get_data_dir()

    config.init_config()

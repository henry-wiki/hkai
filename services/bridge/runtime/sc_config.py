#!/usr/bin/env python3
"""
sc_config.py — 全域唯一嘅 JSON 設定讀取。

職責：
- load_config(path)：讀取 runtime.json，失敗直接 sys.exit(1)
- read_daemon_engine(config)：讀現行引擎（runtime.json 動態值＞靜態 SSOT daemon_engine）——缺／唔合法回預設

注意：
- read_json() 已移至 utils/io.py（通用 I/O 工具）
"""

import sys
from pathlib import Path
from typing import Optional

from ..utils.io import read_json

# 有效引擎（值＝同名設定組／同名 python 套件——services/{name}/）
KNOWN_DAEMON_ENGINES = ("aider_daemon", "goose_daemon")


def load_config(config_path: Optional[Path]) -> dict:
    """讀取 runtime.json，失敗直接 sys.exit(1)。"""
    data = read_json(config_path)
    if data is None:
        from . import sc_logger
        sc_logger.log(f"runtime config unreadable: {config_path}")
        sys.exit(1)
    return data


def read_daemon_engine(config: dict) -> str:
    """讀現行引擎（動態優先——runtime.json 已存值＞manifest 出廠值；皆唔合法 → aider_daemon）。"""
    # 動態層（runtime.json 已存值——PHP 切換寫入處；檔路徑由 bridge 啟動時注入）
    runtime_config_path = config.get("runtime_config_path", "")

    # 判定：有 runtime 路徑 → 讀動態值
    if runtime_config_path != "":
        runtime_config = read_json(Path(runtime_config_path)) or {}
        runtime_daemon_engine = str(runtime_config.get("daemon_engine", ""))

        # 判定：動態值合法 → 回傳
        if runtime_daemon_engine in KNOWN_DAEMON_ENGINES:
            return runtime_daemon_engine

    # 靜態出廠層（manifest）
    static_config_path = config.get("static_config_path", "")

    # 判定：無靜態 SSOT 路徑 → 回預設
    if static_config_path == "":
        return "aider_daemon"

    static_config = read_json(Path(static_config_path)) or {}
    daemon_engine = str(static_config.get("daemon_engine", ""))

    # 判定：值唔係有效引擎（打錯／未設）→ 記 log、回預設
    if daemon_engine not in KNOWN_DAEMON_ENGINES:
        from . import sc_logger
        sc_logger.log(f"daemon_engine invalid: {daemon_engine!r} — fallback aider_daemon")
        return "aider_daemon"

    return daemon_engine

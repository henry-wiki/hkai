#!/usr/bin/env python3
"""
sc_config.py — 全域唯一嘅 JSON 設定讀取。

職責：
- init_config()：開機載入站台固定 runtime.json（data/runtime.json）一次，失敗直接 sys.exit(1)
- get_configs()：取整份已載入設定（init_config 之後必有值）
- get_config(key, sub_key=None)：取第一層值；有 sub_key＝第一層 > 第二層

注意：
- read_json() 已移至 utils/io.py（通用 I/O 工具）
"""

import sys
from typing import Any

from ..utils.io import read_json
from . import sc_logger

# 已載入設定（init_config 一次寫入；其後全程序共用同一份）
_configs: dict = {}


def init_config() -> None:
    """開機載入站台固定 runtime.json（data/runtime.json）——失敗直接 sys.exit(1)。"""
    global _configs

    from .. import context

    config_path = context.RUNTIME_CONFIG_FILE
    data = read_json(config_path)

    if data is None:
        sc_logger.log(f"runtime config unreadable: {config_path}")
        sys.exit(1)

    _configs = data


def get_configs() -> dict:
    """取整份已載入設定（init_config 之後必有值）。"""
    return _configs


def get_config(config_key: str, sub_key: str | None = None) -> Any:
    """取設定值（冇 sub_key＝第一層；有＝第一層 > 第二層；缺 key＝記 log 回 None）。"""
    # 判定：第一層 key 唔存在 → 記 log 並回 None
    if config_key not in _configs:
        sc_logger.log("config key missing: " + config_key)
        return None

    config_value = _configs[config_key]

    # 判定：冇指定第二層 → 回第一層值
    if sub_key is None:
        return config_value

    # 判定：第二層 key 唔存在 → 記 log 並回 None
    if sub_key not in config_value:
        sc_logger.log("config sub key missing: " + config_key + "." + sub_key)
        return None

    return config_value[sub_key]


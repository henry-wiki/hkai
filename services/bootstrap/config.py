#!/usr/bin/env python3
"""
config.py — runtime.json 讀取（全域唯一）——對應 PHP sc_config_Class。

職責：
- init_config()：讀站台固定 runtime.json（<data_dir>/runtime.json）一次，失敗 raise
- get_configs()：取整份已載入設定（init_config 之後必有值）
- get_config(key, sub_key=None)：取第一層值；有 sub_key＝第一層 > 第二層

上游：bridge server、goose_daemon daemon_manager（開機）
下游：bootstrap/path_resolve/（資料根）
"""

import json
import logging
import time
from pathlib import Path
from typing import cast

from .path_resolve.get_data_dir import get_data_dir

# 模組 logger（handler／檔名由程序入口 setup——見 bootstrap/sc_logger.py）
LOGGER = logging.getLogger(__name__)

# 已載入設定（init_config 一次寫入；其後全程序共用同一份）
_configs: dict[str, object] = {}


def _read_json(path: Path) -> dict[str, object] | None:
    """防禦性讀取 JSON（失敗回 None，唔拋例外）。"""
    # 判定：檔案唔存在 → None
    if path.is_file() is False:
        return None

    try:
        with open(path, "r", encoding="utf-8") as file_handle:
            payload = cast("object", json.load(file_handle))
    except Exception:  # noqa: BLE001
        return None

    # 判定：唔係物件 → None
    if isinstance(payload, dict) is False:
        return None

    return cast("dict[str, object]", payload)


def init_config() -> None:
    """開機載入站台固定 runtime.json（<data_dir>/runtime.json）——失敗記錄後 raise。"""
    global _configs

    data_dir = get_data_dir()
    config_path = Path(data_dir) / "runtime.json"
    data = _read_json(config_path)

    # PHP 寫 runtime.json 同拉起 bridge 有機會重疊（讀到半份檔）→ 短暫重試
    retry_index = 0

    while data is None and retry_index < 5:
        time.sleep(0.2)
        data = _read_json(config_path)
        retry_index = retry_index + 1

    # 判定：仍然讀唔到 → 記錄後 raise（死亡與否交程序入口決定）
    if data is None:
        LOGGER.error("runtime config unreadable: %s", config_path)
        raise RuntimeError("runtime config unreadable: " + str(config_path))

    _configs = data


def get_configs() -> dict[str, object]:
    """取整份已載入設定（init_config 之後必有值）。"""
    return _configs


def get_config(config_key: str, sub_key: str | None = None) -> object:
    """取設定值（冇 sub_key＝第一層；有＝第一層 > 第二層；缺 key 回 None）。"""
    # 判定：第一層 key 唔存在 → None
    if config_key not in _configs:
        return None

    config_value = _configs[config_key]

    # 判定：冇指定第二層 → 回第一層值
    if sub_key is None:
        return config_value

    sub_config = cast("dict[str, object]", config_value)

    # 判定：第二層 key 唔存在 → None
    if sub_key not in sub_config:
        return None

    return sub_config[sub_key]

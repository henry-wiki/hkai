#!/usr/bin/env python3
"""
env.py — .env 唯一入口（讀取＋API key 注入）——對應 PHP sc_env_Class。

職責：
- init_env()         讀整份 .env → dict（唯讀；唔改 os.environ）
- get_env()          取 .env 值（缺 key → None）
- get_os_env()       取作業系統環境變數（os.environ；缺 key → None）
- read_api_keys_file()／load_api_keys()  讀 <data_dir>/config/api_keys.json → 寫入 os.environ（.env 只做 fallback）

上游：bridge main、goose_daemon daemon_manager（開機 init）、daemon_wake（get_env）
下游：python-dotenv（.env 標準解析）；bootstrap/path_resolve/（資料根——函數內延遲 import）
"""
# pyright: reportImportCycles=false
# （理由：env ⇄ path_resolve.get_data_dir 互為入口——env 側用函數內延遲 import 接駁，環屬良性）

import json
import logging
import os
from pathlib import Path
from typing import cast

from dotenv import dotenv_values

from .path import ENV_FILE

# 模組 logger（handler／檔名由程序入口 setup——見 bootstrap/sc_logger.py）
LOGGER = logging.getLogger(__name__)

# 已讀入嘅 .env（key → value；init_env() 建立）
_env_values: dict[str, str] = {}


def init_env() -> dict[str, str]:
    """讀整份 .env → dict（標準 dotenv 語法：引號、轉義、註釋、export 全部照規範）。

    讀取失敗（權限／編碼等）→ 記錄後 raise（檔案唔存在＝空表，唔算失敗）。
    """
    global _env_values

    try:
        env_values = dotenv_values(ENV_FILE, interpolate=False)
    except Exception:  # noqa: BLE001
        LOGGER.exception("env read failed: %s", ENV_FILE)
        raise

    _env_values = {str(env_key): str(env_value or "") for env_key, env_value in env_values.items()}

    return _env_values


def get_env(env_key: str) -> str | None:
    """取 .env 值（缺 key → None）；未載入時自動讀一次。"""
    # 判定：記憶體未有 .env → 先讀一次（保證任何呼叫次序都拿到值）
    if not _env_values:
        _ = init_env()

    # 判定：key 唔存在 → None
    if env_key not in _env_values:
        return None

    return _env_values[env_key]


def get_os_env(env_key: str) -> str | None:
    """取作業系統環境變數（os.environ——唔經 .env；缺 key → None）。"""
    # 判定：系統環境冇呢個 key → None
    if env_key not in os.environ:
        return None

    return os.environ[env_key]


def read_api_keys_file() -> dict[str, str]:
    """讀 <data_dir>/config/api_keys.json（用戶可設定 key；唔存在／壞 → 空表）。"""
    # 延遲 import（避開 path → env → path 循環）
    from .path_resolve.get_data_dir import get_data_dir

    data_dir = get_data_dir()
    config_file = Path(data_dir) / "config" / "api_keys.json"

    # 判定：檔案唔存在 → 空表
    if config_file.is_file() is False:
        return {}

    try:
        payload = cast("object", json.loads(config_file.read_text(encoding="utf-8")))
    except Exception:  # noqa: BLE001
        return {}

    # 判定：唔係物件 → 空表
    if isinstance(payload, dict) is False:
        return {}

    return cast("dict[str, str]", payload)


def load_api_keys() -> None:
    """讀 api_keys.json（優先）＋ .env（fallback）→ 將 *_API_KEY 寫入 os.environ。"""
    file_values = read_api_keys_file()
    env_values = init_env()

    for env_key, env_value in env_values.items():
        # 判定：非 <NAME>_API_KEY 格式 → 跳過
        if not env_key.endswith("_API_KEY"):
            continue

        # 判定：JSON 已有值 → 唔用 .env（JSON 為準）
        if env_key in file_values:
            continue

        # 判定：清空／裸 KEY → 跳過（唔寫入）
        if env_value == "":
            continue

        os.environ[env_key] = env_value

    for env_key, env_value in file_values.items():
        # 判定：非 <NAME>_API_KEY 格式 → 跳過
        if not env_key.endswith("_API_KEY"):
            continue

        os.environ[env_key] = str(env_value)

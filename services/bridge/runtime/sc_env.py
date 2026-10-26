#!/usr/bin/env python3
"""
sc_env.py

.env 唯一入口（讀取＋API key 注入同一模組——值只有一個真相）。

職責：
- init_env()        讀整份 .env → dict（唯讀；唔改 os.environ）
- get_env()         取 dict 值（缺 key → 記 log＋None）
- load_api_keys()   讀 .env → 將 *_API_KEY 寫入 os.environ（有值覆寫）

上游：bridge main（開機 init_env）、daemon_wake（get_env）
下游：python-dotenv（dotenv_values——quote／空格／跳脫邊界交標準庫）
"""

import os

from dotenv import dotenv_values

from ..context import ENV_FILE
from . import sc_logger

# 已讀入嘅 .env（key → value；init_env() 建立）
_env_values: dict[str, str | None] = {}


def init_env() -> dict[str, str | None]:
    """讀整份 .env（含任何 key——唔限 API）→ 記憶體 dict；回傳新表。

    讀取失敗（權限／編碼等）→ 記 log 後直接退出（冇 .env 就冇 key，唔可以繼續行）。
    """
    global _env_values

    try:
        env_values = dotenv_values(ENV_FILE, interpolate=False)
    except Exception as exc:  # noqa: BLE001
        sc_logger.log("env read failed: " + str(exc))
        from . import lifecycle

        lifecycle.exit("env read failed", 1)

    _env_values = dict(env_values)
    return _env_values


def get_env(env_key: str) -> str | None:
    """取 .env 值（缺 key → 記 log 回 None；裸 key／無值 → None）。"""
    # 判定：key 唔存在 → 記 log 並回 None
    if env_key not in _env_values:
        sc_logger.log("env key missing: " + env_key)
        return None

    return _env_values[env_key]


def load_api_keys() -> None:
    """讀 .env → 將 *_API_KEY 寫入 os.environ（檔案為唯一真相——有值覆寫）。"""
    values = init_env()

    for env_key, env_value in values.items():
        # 判定：非 <NAME>_API_KEY 格式 → 跳過
        if not env_key.endswith("_API_KEY"):
            continue

        # 判定：清空／裸 KEY → 跳過（唔寫入）
        if env_value is None or env_value == "":
            continue

        os.environ[env_key] = env_value

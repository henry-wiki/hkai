#!/usr/bin/env python3
"""
context.py

統一橋接層（Service Locator）——集中管理所有全局單例與上下文注入。

其他模組只需 `from .context import xxx`，無需關心底層初始化細節。
"""

import time

from . import BASE_DIR, DATA_DIR, ROOT_DIR, RUNTIME_DIR

# 心跳狀態目錄（站台根 data/process_status——核心路徑，唔靠 runtime.json）
PROCESS_DIR = DATA_DIR / "process_status"

# 程序台帳（append-only JSONL）
PROCESS_FILE_LIST = PROCESS_DIR / "process_list.jsonl"

# 程序身份（心跳／台帳用——單一來源）
PROGRAM_NAME = "bridge"
PROGRAM_LABEL = "bridge.main"

# 閒置自殺上限（秒——核心行為，唔放 runtime.json）
IDLE_EXIT_SEC = 120

# ==========================================
# 統一獲取全局單例（Bridging Functions）
# ==========================================

def timer():
    """取得 timer_class 單例。"""
    from .utils.timer import timer_class
    return timer_class.get_instance()


def get_datetime() -> str:
    """取得秒精度時間字串（Y-m-d H:M:S）。"""
    return timer().get_datetime()


def get_timestamp() -> int:
    """取得 epoch 秒（int——同 PHP time()）。"""
    return int(time.time())


def strtotime(timestamp: int) -> str:
    """epoch 秒 → Y-m-d H:i:s 格式（PHP date() 同格式）。"""
    return timer().strtotime(timestamp)


def process_helper():
    """取得 process_helper_class 單例。"""
    from .utils.process_helper import process_helper_class
    return process_helper_class.get_instance()
def server():
    """取得 server_class 單例。"""
    from .server.server import server_class
    return server_class.get_instance()


def load_config(config_path):
    """載入 runtime.json 設定（失敗 exit）。"""
    from .runtime.sc_config import load_config as _load_config
    return _load_config(config_path)


# 對外匯出（供 `from .context import *`）——本檔 bridging 函數
__all__ = [
    "BASE_DIR",
    "DATA_DIR",
    "IDLE_EXIT_SEC",
    "PROCESS_DIR",
    "PROCESS_FILE_LIST",
    "PROGRAM_LABEL",
    "PROGRAM_NAME",
    "ROOT_DIR",
    "RUNTIME_DIR",
    "get_datetime",
    "get_timestamp",
    "load_config",
    "process_helper",
    "server",
    "strtotime",
    "timer",
]

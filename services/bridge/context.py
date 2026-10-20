#!/usr/bin/env python3
"""
context.py

統一橋接層（Service Locator）——集中管理所有全局單例與上下文注入。

其他模組只需 `from .context import xxx`，無需關心底層初始化細節。
"""

from . import BASE_DIR, RUNTIME_DIR

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
    "RUNTIME_DIR",
    "timer",
    "get_datetime",
    "strtotime",
    "process_helper",
    "server",
    "load_config",
]

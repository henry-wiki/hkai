#!/usr/bin/env python3
"""
activity.py — 活動標記（bridge 專用；只依賴標準庫，可獨立搬走）

語義：bridge.last_activity 的 mtime＝「最後一次有人用」的時間。

同 heartbeat.py 分工：
- heartbeat  程序自己寫（我還在）
- activity   使用者請求寫（有人用）

三支：
- touch()    建立／更新 mtime（每個 HTTP request 叫）
- age_sec()  距今秒數（檔不存在 → 無限大，視為已過期）
- clear()    刪除檔案（正常退出——唔留殘檔）
"""

import time
from pathlib import Path

from ..context import PROCESS_DIR, PROGRAM_NAME


def _get_activity_file() -> Path:
    """活動檔路徑（PROCESS_DIR/PROGRAM_NAME.last_activity）。"""
    return Path(PROCESS_DIR) / (PROGRAM_NAME + ".last_activity")


def touch() -> None:
    """建立／更新活動檔 mtime（失敗唔影響主流程）。"""
    activity_file = _get_activity_file()

    try:
        activity_file.parent.mkdir(parents=True, exist_ok=True)
        activity_file.touch(exist_ok=True)
    except OSError:
        pass


def age_sec() -> float:
    """活動檔距今秒數（檔不存在／讀不到 → 無限大，視為已過期）。"""
    activity_file = _get_activity_file()

    try:
        return time.time() - activity_file.stat().st_mtime
    except OSError:
        return float("inf")


def clear() -> None:
    """刪除活動檔（正常退出——唔留殘檔）。"""
    activity_file = _get_activity_file()

    try:
        if activity_file.exists():
            activity_file.unlink()
    except OSError:
        pass

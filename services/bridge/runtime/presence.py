#!/usr/bin/env python3
"""
presence.py — 頁面在線標記（bridge 專用；只依賴標準庫，可獨立搬走）

語義：bridge.last_presence 的 mtime＝「最後一次有頁面連住」的時間；
      檔案內容＝同一時間的人類可讀 datetime（方便直接開檔查看）。
server_presence.py 每條 SSE 連線存活期間不斷 touch()；斷線後唔再 touch，
年齡自然增長——heartbeat 用 age_sec() 判斷係唔係已經冇人。

三支：
- touch()    建立／更新 mtime（SSE 連線開始＋每次 ping）
- age_sec()  距今秒數（檔不存在 → 無限大，視為已過期）
- clear()    刪除檔案（程序退出——唔留殘檔）
"""

import time

from ..context import PRESENCE_FILE, get_datetime


def touch() -> None:
    """建立／更新在線標記（內容＝datetime；mtime 同步更新；失敗唔影響主流程）。"""
    presence_file = PRESENCE_FILE

    try:
        presence_file.parent.mkdir(parents=True, exist_ok=True)
        presence_file.write_text(get_datetime(), encoding="utf-8")
    except OSError:
        pass


def age_sec() -> float:
    """在線標記距今秒數（檔不存在／讀不到 → 無限大，視為已過期）。"""
    presence_file = PRESENCE_FILE

    try:
        return time.time() - presence_file.stat().st_mtime
    except OSError:
        return float("inf")


def stop() -> None:
    """刪除在線標記（程序退出——唔留殘檔）。"""
    presence_file = PRESENCE_FILE

    try:
        if presence_file.exists():
            presence_file.unlink()
    except OSError:
        pass

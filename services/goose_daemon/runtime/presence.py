#!/usr/bin/env python3
"""
presence.py — 工人活躍標記（goose_daemon；同 bridge 同結構，判斷條件唔同）

語義：goose_daemon.last_presence 的 mtime ＝「最後一次有工人在跑」的時間；
      檔案內容＝同一時間的人類可讀 datetime（方便直接開檔查看）。
daemon 每拍見到名冊有工人就 touch()；名冊清零後唔再 touch，年齡自然增長——
exit_when_idle() 用 age_sec() 判斷係唔係已經冇工人（夠門檻即連子程序收檔）。

四支：
- touch()           建立／更新活躍標記（有工人時每拍）
- age_sec()         距今秒數（檔不存在／讀不到 → 無限大，視為已過期）
- stop()            刪除標記（程序退出——唔留殘檔）
- exit_when_idle()  年齡夠門檻 → 連子程序自殺（bridge 由 heartbeat 做嘅同類決策）
"""

import time

from .. import context
from . import lifecycle, sc_logger


def touch() -> None:
    """建立／更新活躍標記（內容＝datetime；mtime 同步更新；失敗唔影響主流程）。"""
    presence_file = context.PRESENCE_FILE

    try:
        presence_file.parent.mkdir(parents=True, exist_ok=True)
        presence_file.write_text(context.get_datetime(), encoding="utf-8")
    except OSError:
        pass


def age_sec() -> float:
    """活躍標記距今秒數（檔不存在／讀不到 → 無限大，視為已過期）。"""
    presence_file = context.PRESENCE_FILE

    try:
        return time.time() - presence_file.stat().st_mtime
    except OSError:
        return float("inf")


def stop() -> None:
    """刪除活躍標記（程序退出——唔留殘檔）。"""
    presence_file = context.PRESENCE_FILE

    try:
        if presence_file.exists():
            presence_file.unlink()
    except OSError:
        pass


def exit_when_idle() -> None:
    """活躍標記年齡 ≥ IDLE_EXIT_SEC → 連子程序自殺（daemon 冇工人太久了）。"""
    idle_sec = age_sec()

    # 判定：未夠門檻 → 繼續
    if idle_sec < context.IDLE_EXIT_SEC:
        return

    sc_logger.log(f"idle {idle_sec:.0f}s, exit with children")
    lifecycle.exit("idle timeout", 0)

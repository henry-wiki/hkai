#!/usr/bin/env python3
"""
process.py — 程序身份（每個程序自己一份；啟動即記自己同母親）

責任：只做記錄同感應，唔殺程序（殺交 lifecycle.exit／exit_children）

三支：
- init()            啟動即記：自己 pid ＋ 母親 pid＋建立時間
- self_pid()        本程序 pid
- mother_pid()      母親 pid（記錄值；0＝冇母親）
- get_live_mother_pid()  母親仍在 → pid；已死／被重用 → 0
- mother_is_gone()  母親唔在／被重用 → True
"""

import os

import psutil

from . import sc_logger

# 本程序 pid（init 時記錄）
_self_pid = 0

# 母親 pid（init 時記錄；0＝冇母親）
_mother_pid = 0

# 母親建立時間（防 pid 重用——記錄時母親仍然在世才有值）
_mother_create_time = None


def init() -> None:
    """啟動即記：自己 pid ＋ 母親 pid＋建立時間。"""
    global _self_pid, _mother_pid, _mother_create_time

    _self_pid = os.getpid()

    # 母親＝本程序嘅 OS 父（拉起一刻最準）
    _mother_pid = os.getppid()

    # 判定：ppid ≤ 1（POSIX 已被 reparent 去 init）→ 當冇母親
    if _mother_pid <= 1:
        _mother_pid = 0
        _mother_create_time = None

        return

    _mother_create_time = _get_process_create_time(_mother_pid)


def self_pid() -> int:
    """本程序 pid。"""
    return _self_pid


def _get_process_create_time(process_pid: int) -> "int | None":
    """讀程序建立時間（整數毫秒；讀唔到 → None）。

    用整數毫秒：浮點尾位跨語言／JSON 序列化會漂移，整數先可以精確 == 對比。
    """
    try:
        create_time = psutil.Process(process_pid).create_time()
    except psutil.Error as exc:
        sc_logger.log("create_time read failed pid=" + str(process_pid) + " | " + str(exc))
        return None

    # 轉整數毫秒（截斷——寫入同比對用同一個公式）
    return int(create_time * 1000)


def self_create_time() -> int:
    """自己程序建立時間（整數毫秒；讀唔到 → 0）。"""
    return _get_process_create_time(_self_pid) or 0


def parent_create_time() -> int:
    """母親（父）程序建立時間（整數毫秒；未記錄／讀唔到 → 0）。"""
    return _mother_create_time or 0


def mother_pid() -> int:
    """母親 pid（0＝冇母親）。"""
    return _mother_pid


def get_live_mother_pid() -> int:
    """母親仍在 → pid；已死／被重用 → 0。"""
    # 判定：母親已死或號碼被重用 → 回 0
    if mother_is_gone() is True:
        return 0

    return _mother_pid


def mother_is_gone() -> bool:
    """母親唔在／被重用 → True（冇母親 → False）。"""
    # 判定：冇母親 pid → 唔判斷
    if _mother_pid <= 0:
        return False

    # 判定：pid 唔存在 → 母親已死
    if psutil.pid_exists(_mother_pid) is False:
        return True

    # 判定：未記錄建立時間 → 只能信 pid（少數情況）
    if _mother_create_time is None:
        return False

    # 判定：pid 存在但建立時間唔同 → 原母親已死（號碼被重用）
    current_create_time = _get_process_create_time(_mother_pid)

    if current_create_time is None:
        return True

    return current_create_time != _mother_create_time

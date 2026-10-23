#!/usr/bin/env python3
"""
lifecycle.py — 程序生死契約（bridge 專用；只依賴標準庫＋psutil，可獨立搬走）

三種殺法（心跳已分家→ heartbeat.py；bridge 其他檔案禁止直接 os._exit／terminate／kill）：

1. exit(reason)            自殺（連子孫）：殺子孫 → 走 (3) 自殺
2. exit_children()         殺子孫（唔殺自己）：terminate 自己全部子孫 → 逾時 kill
3. exit_self(reason)       只自殺：清狀態／在線檔 → 記 log → os._exit（死亡出口）
4. exit_when_mother_gone() 母親唔在 → 走 (3) 只自殺（由 heartbeat 每秒呼叫）

「子孫」定義＝psutil 子樹（recursive）——包括 CREATE_NEW_PROCESS_GROUP 拉起嘅程序；
母親一死，子孫會被系統重新掛到 root，就唔再屬於原本子樹。
"""

import os

import psutil

from .. import context
from . import presence, process

# terminate 之後等子孫自己收工嘅上限（秒——逾時即 kill）
TERMINATE_WAIT_SEC = 3.0


def _safe_log(message: str) -> None:
    """記 log（logger 問題絕不影響生死流程）。"""
    # 判定：logger 失敗 → 靜靜略過（生死流程唔可以因記錄失敗而中斷）
    try:
        from . import sc_logger

        sc_logger.log(message)
    except Exception:  # noqa: BLE001, S110
        pass


def exit(reason: str = "", exit_code: int = 0) -> None:
    """自殺（連子孫）：先殺子孫 → 再自殺。"""
    exit_children()
    exit_self(reason, exit_code)


def exit_self(reason: str = "", exit_code: int = 0) -> None:
    """只自殺：清狀態／在線檔 → 記 log → os._exit（死亡出口）。"""
    # 刪自己狀態檔（heartbeat 擁有——延遲 import 免循環）
    from . import heartbeat

    heartbeat.remove_heartbeat_file()
    presence.stop()

    _safe_log("exit: " + (reason if reason != "" else "no reason") + " (code=" + str(exit_code) + ")")
    os._exit(exit_code)


def exit_when_mother_gone() -> None:
    """母親唔在 → 只自殺（子孫自己監視母親，3 秒內自殺）。"""
    # 判定：本程序唔依賴母親（設定喺 context）→ 唔判斷
    if context.NEED_MOTHER is False:
        return

    # 判定：母親仍在 → 唔做嘢
    if process.mother_is_gone() is False:
        return

    exit_self("mother pid " + str(process.mother_pid()) + " gone", 0)


def exit_children() -> None:
    """殺死自己全部子孫（唔殺自己）：terminate → 逾時 kill。"""
    child_processes = []

    # 取自己嘅全部子孫（遞歸——只向下，唔掂母親）
    try:
        child_processes = psutil.Process(process.self_pid()).children(recursive=True)
    except psutil.Error:
        child_processes = []

    # 先客氣：逐個 terminate
    for child_process in child_processes:
        try:
            child_process.terminate()
        except psutil.Error:
            pass

    # 等子孫自己收工（逾時就轉 kill；權限不足／已死等錯誤唔可以中斷死亡流程）
    try:
        _gone_processes, alive_processes = psutil.wait_procs(child_processes, timeout=TERMINATE_WAIT_SEC)
    except psutil.Error:
        alive_processes = child_processes

    # 判定：逾時未死 → 強制 kill
    for alive_process in alive_processes:
        try:
            alive_process.kill()
        except psutil.Error:
            pass






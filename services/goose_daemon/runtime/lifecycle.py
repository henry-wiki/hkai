#!/usr/bin/env python3
"""
lifecycle.py — 程序生死契約（daemon 專用；只依賴標準庫＋psutil，可獨立搬走）

三種殺法（其他檔案禁止直接 os._exit／terminate／kill）：

1. exit(reason)             自殺（連子孫）：殺子孫 → 走 (3) 自殺
2. exit_children()          殺子孫（唔殺自己）：terminate 自己全部子孫 → 逾時 kill
3. exit_self(reason)        只自殺：清心跳檔 → 記 log → os._exit（死亡出口）
5. exit_child(pid, reason)  主動殺指定子程序（terminate → 逾時 kill；唔殺自己）

「子孫」定義＝psutil 子樹（recursive）——包括 worker／agent 程序。
"""

import os
import threading
import time
from collections.abc import Callable
from typing import NoReturn

import psutil

from .. import context
from . import build_file, process

# terminate 之後等子孫自己收工嘅上限（秒——逾時即 kill）
TERMINATE_WAIT_SEC = 3.0

# 背景監看執行緒（watch_runtime 建立；同一程序只一條）
_watch_thread = None

# 收場處理（daemon 注入經理協議；None＝直接殺子孫自殺）
_shutdown_handler = None

# 心跳檔清理回呼（heartbeat／heartbeat_worker 開機時注入；None＝唔清）
_heartbeat_cleaner: Callable[[], None] | None = None


def _safe_log(message: str) -> None:
    """記 log（logger 問題絕不影響生死流程）。"""
    # 判定：logger 失敗 → 靜靜略過（生死流程唔可以因記錄失敗而中斷）
    try:
        from . import sc_logger

        sc_logger.log(message)
    except Exception:  # noqa: BLE001, S110
        pass


def set_heartbeat_cleaner(heartbeat_cleaner: Callable[[], None]) -> None:
    """注入心跳檔清理回呼（各心跳模組開機時登記——lifecycle 唔需要 import heartbeat）。"""
    global _heartbeat_cleaner

    _heartbeat_cleaner = heartbeat_cleaner


def set_shutdown_handler(shutdown_handler) -> None:
    """注入收場處理（daemon 經理協議——先禮後兵；未注入＝直接殺子孫自殺）。"""
    global _shutdown_handler

    _shutdown_handler = shutdown_handler


def exit(reason: str = "", exit_code: int = 0) -> NoReturn:
    """自殺（連子孫）：先殺子孫 → 再自殺。

    判定：已注入 shutdown handler（daemon 經理協議）→ 交佢收場（佢最後自己 exit_self）。
    """
    # 判定：有 shutdown handler → 交經理協議（先禮後兵）
    if _shutdown_handler is not None:
        _shutdown_handler(reason, exit_code)

    exit_children()
    exit_self(reason, exit_code)


def exit_self(reason: str = "", exit_code: int = 0) -> NoReturn:
    """只自殺：清心跳檔 → 清台帳自己嗰行 → 記 log → os._exit（死亡出口）。"""
    # 判定：有心跳清理回呼（心跳模組已登記）→ 清
    if _heartbeat_cleaner is not None:
        _heartbeat_cleaner()

    process.sweep_dead_lines(os.getpid())

    _safe_log("exit: " + (reason if reason != "" else "no reason") + " (code=" + str(exit_code) + ")")
    os._exit(exit_code)


def exit_child(process_pid: int, reason: str = "") -> None:
    """主動殺指定子程序（terminate → 逾時 kill；唔殺自己）。"""
    try:
        child_process = psutil.Process(int(process_pid))
    except psutil.Error:
        return

    try:
        child_process.terminate()
    except psutil.Error:
        return

    try:
        _gone_processes, alive_processes = psutil.wait_procs([child_process], timeout=TERMINATE_WAIT_SEC)
    except psutil.Error:
        alive_processes = [child_process]

    # 判定：逾時未死 → 強制 kill
    for alive_process in alive_processes:
        try:
            alive_process.kill()
        except psutil.Error:
            pass

    _safe_log("exit child pid=" + str(process_pid) + " reason=" + reason)


def watch_runtime() -> None:
    """監看 runtime.json／BUILD（背景執行緒，1 秒一拍）——檔消失或 BUILD 變更 → 自殺。"""
    global _watch_thread

    # 判定：已有監看執行緒喺跑 → 唔重複開
    if _watch_thread is not None and _watch_thread.is_alive():
        return

    _watch_thread = threading.Thread(target=_watch_loop, daemon=True, name="watch_runtime")
    _watch_thread.start()


def _watch_loop() -> None:
    """背景監看：每拍檢查 runtime.json 存在＋BUILD 有冇變。"""
    build_file.initialize()

    while True:
        # 判定：runtime.json 已被刪（建置／清理）→ 自我退出，不留孤兒心跳檔
        if context.RUNTIME_CONFIG_FILE.is_file() is False:
            exit("runtime config removed")

        build_watch_result = build_file.changed()

        # 判定：BUILD 已變（站台已重新發佈）→ 記錄後自我退出，等新版本再拉起
        if build_watch_result["changed"] is True:
            exit("BUILD changed " + build_watch_result["previous"] + " -> " + build_watch_result["current"])

        time.sleep(1.0)


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

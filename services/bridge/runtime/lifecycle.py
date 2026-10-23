#!/usr/bin/env python3
"""
lifecycle.py — 程序生死契約（bridge 專用；只依賴標準庫＋psutil，可獨立搬走）

三種殺法（心跳已分家→ heartbeat.py；bridge 其他檔案禁止直接 os._exit／terminate／kill）：

1. exit_with_children()   自殺：terminate 自己全部子孫 → 逾時 kill → os._exit
2. kill_tree()            被殺：由上一層程序呼叫——殺指定 pid 同佢全部子孫
3. watch_supervisor()     孤兒自檢：背景執行緒睇母親 pid；母親死 → 走 (1) 自殺

「子孫」定義＝psutil 子樹（recursive）——包括 CREATE_NEW_PROCESS_GROUP 拉起嘅程序；
母親一死，子孫會被系統重新掛到 root，就唔再屬於原本子樹。
"""

import os
import threading
import time
from pathlib import Path

import psutil

from . import heartbeat

# terminate 之後等子孫自己收工嘅上限（秒）
TERMINATE_WAIT_SEC = 5.0


def _safe_log(message: str) -> None:
    """記 log（logger 問題絕不影響生死流程）。"""
    try:
        from . import sc_logger

        sc_logger.log(message)
    except Exception:
        pass


def exit_with_children(exit_code: int = 1, reason: str = "") -> None:
    """自殺：先 terminate 全部子孫，逾時 kill，最後 os._exit（唔行 atexit／cleanup）。"""
    child_processes = []

    # 取自己嘅全部子孫（遞歸——只向下，唔掂母親）
    try:
        child_processes = psutil.Process(os.getpid()).children(recursive=True)
    except psutil.Error:
        child_processes = []

    # 先客氣：逐個 terminate
    for child_process in child_processes:
        try:
            child_process.terminate()
        except psutil.Error:
            pass

    # 等子孫自己收工（逾時就轉 kill）
    gone_processes, alive_processes = psutil.wait_procs(child_processes, timeout=TERMINATE_WAIT_SEC)

    # 判定：逾時未死 → 強制 kill
    for alive_process in alive_processes:
        try:
            alive_process.kill()
        except psutil.Error:
            pass

    _safe_log("exit_with_children pid=" + str(os.getpid()) + " code=" + str(exit_code) + " reason=" + str(reason))
    os._exit(exit_code)


def kill_tree(target_pid: int, reason: str = "") -> bool:
    """被殺（母親殺子孫）：殺指定 pid 同佢全部子孫；目標係自己則轉自殺。"""
    # 判定：目標係自己 → 走自殺路徑（避免自己死一半、子孫留低）
    if int(target_pid) == os.getpid():
        exit_with_children(0, reason)
        return True

    try:
        target_process = psutil.Process(int(target_pid))
    except psutil.Error:
        return False

    try:
        process_tree = [target_process] + target_process.children(recursive=True)
    except psutil.Error:
        process_tree = [target_process]

    # 先 terminate（子孫先、母親後——避免母親先死令子孫變孤兒）
    for process_item in reversed(process_tree):
        try:
            process_item.terminate()
        except psutil.Error:
            pass

    # 等程序樹自己收工（逾時就 kill）
    gone_processes, alive_processes = psutil.wait_procs(process_tree, timeout=TERMINATE_WAIT_SEC)

    # 判定：逾時未死 → 強制 kill
    for alive_process in alive_processes:
        try:
            alive_process.kill()
        except psutil.Error:
            pass

    _safe_log("kill_tree pid=" + str(target_pid) + " reason=" + str(reason))
    return True


def watch_supervisor(mother_pid: int, interval_sec: float = 5.0, grace_sec: float = 30.0, reason: str = "") -> threading.Thread:
    """孤兒自檢：背景執行緒定期檢查母親 pid；母親死 → 自殺（連子孫）。回傳執行緒。"""
    watch_started_at = time.time()

    def _watch_loop() -> None:
        """無限巡邏直至自殺（程序死咗執行緒自然消失）。"""
        while True:
            time.sleep(interval_sec)
            elapsed_sec = time.time() - watch_started_at

            # 寬限期內唔判斷（開機時母親可能未開始）
            if elapsed_sec < grace_sec:
                continue

            # 判定：母親 pid 已死 → 孤兒 → 自殺
            if psutil.pid_exists(int(mother_pid)) is False:
                exit_with_children(0, reason or "mother pid " + str(mother_pid) + " gone")

    watch_thread = threading.Thread(target=_watch_loop, name="lifecycle_watch_supervisor", daemon=True)
    watch_thread.start()

    return watch_thread


def _cli_main() -> None:
    """CLI 入口（CI 測試用）——python -m bridge.runtime.lifecycle <command> ..."""
    import argparse

    parser = argparse.ArgumentParser(prog="lifecycle.py")
    sub_parsers = parser.add_subparsers(dest="command", required=True)

    # 子命令①：自殺（連子孫）
    exit_parser = sub_parsers.add_parser("exit", help="自殺（連子孫）")
    exit_parser.add_argument("--code", type=int, default=0)
    exit_parser.add_argument("--reason", default="cli")

    # 子命令②：被殺（殺指定 pid 子樹）
    kill_parser = sub_parsers.add_parser("kill-tree", help="殺指定 pid 同全部子孫")
    kill_parser.add_argument("--pid", type=int, required=True)
    kill_parser.add_argument("--reason", default="cli")

    # 子命令③：孤兒自檢（前景阻塞）
    watch_parser = sub_parsers.add_parser("watch", help="睇母親 pid；死咗自殺")
    watch_parser.add_argument("--pid", type=int, required=True)
    watch_parser.add_argument("--interval", type=float, default=5.0)
    watch_parser.add_argument("--grace", type=float, default=30.0)

    cli_args = parser.parse_args()

    # 判定：exit → 自殺
    if cli_args.command == "exit":
        exit_with_children(cli_args.code, cli_args.reason)

    # 判定：kill-tree → 殺子樹（成功 exit 0；目標不存在 exit 1）
    if cli_args.command == "kill-tree":
        is_killed = kill_tree(cli_args.pid, cli_args.reason)
        raise SystemExit(0 if is_killed else 1)

    # 判定：watch → 前景阻塞（執行緒 daemon——程序退出即消失）
    if cli_args.command == "watch":
        watch_supervisor(cli_args.pid, cli_args.interval, cli_args.grace)

        while True:
            time.sleep(3600)


# 判定：以模組方式執行（python -m bridge.runtime.lifecycle）→ 入 CLI
if __name__ == "__main__":
    _cli_main()

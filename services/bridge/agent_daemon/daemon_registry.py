#!/usr/bin/env python3
"""
daemon_registry.py — PID 登記＋跨平台存活檢查。

職責：
- is_daemon_alive(agent_daemon)：供 daemon_wake 呼叫（agent_daemon＝現行引擎；掃 process_status 心跳）
- _is_process_alive(pid)：跨平台 PID 檢查（私有）
"""

import psutil

from .. import context
from ..runtime import process


def is_daemon_alive(agent_daemon: str) -> bool:
    """檢查 agent daemon 是否生存（掃 process_status/{agent_daemon}.*.json——pid 活＋父程序＝本 bridge）。"""
    process_dir = context.PROCESS_DIR

    # 判定：心跳目錄唔存在 → False
    if not process_dir.is_dir():
        return False

    # 逐個心跳檔（{agent_daemon}.{pid}.json）比對：pid 由檔名解析
    for daemon_process_file in process_dir.glob(agent_daemon + ".*.json"):
        # 檔名格式 {program}.{pid}——取最後一段做 pid
        daemon_process_name = daemon_process_file.stem
        daemon_process_parts = daemon_process_name.split(".")
        daemon_pid_text = daemon_process_parts[-1]

        try:
            daemon_pid = int(daemon_pid_text)
        except ValueError:
            continue

        # 判定：pid 唔活 → 跳過（強殺殘檔）
        if not _is_process_alive(daemon_pid):
            continue

        # 判定：父程序唔係本 bridge → 唔係自己嗰隻
        if _get_parent_pid(daemon_pid) != process.self_pid():
            continue

        return True

    return False


def _get_parent_pid(process_pid: int) -> int:
    """讀程序父 pid（讀唔到 → 0）。"""
    try:
        return psutil.Process(process_pid).ppid()
    except psutil.Error:
        return 0


def _is_process_alive(process_pid: int) -> bool:
    """本機程序存在檢查（psutil——Windows／POSIX 同一套）。"""
    if process_pid <= 0:
        return False

    try:
        return psutil.Process(process_pid).is_running()
    except psutil.Error:
        return False

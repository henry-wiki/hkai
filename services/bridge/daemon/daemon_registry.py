#!/usr/bin/env python3
"""
daemon_registry.py — PID 登記＋跨平台存活檢查。

職責：
- is_daemon_alive(config, daemon_name)：供 daemon_wake 呼叫（daemon_name＝現行引擎；掃 process_status 心跳）
- register_pid(pid, config)：供 daemon_manager 呼叫
- _is_process_alive(pid)：跨平台 PID 檢查（私有）
"""

import ctypes
import os
import sys
import time
from pathlib import Path


def is_daemon_alive(config: dict, daemon_name: str) -> bool:
    """供 daemon_wake 呼叫（掃 process_status/{daemon_name}.*.json——pid 活＋心跳新鮮＝活；daemon_name＝現行引擎）。"""
    storage_dir = config.get("storage_dir", "")
    folders = config.get("folders", {})
    status_dir = Path(storage_dir) / folders.get("process_status", "process_status")

    # 判定：心跳目錄唔存在 → False
    if not status_dir.is_dir():
        return False

    # 逐個心跳檔（{daemon_name}.{pid}.json）比對：pid 活＋心跳新鮮先算係我哋嘅 daemon
    for status_file in status_dir.glob(daemon_name + ".*.json"):
        # pid 由檔名取（唔信檔案內容——檔名係寫入端契約）
        try:
            status_pid = int(status_file.stem.split(".")[-1])
        except ValueError:
            continue

        # 判定：pid 唔活 → 跳過（強殺殘檔）
        if not _is_process_alive(status_pid):
            continue

        # 判定：心跳檔過期（> 60 秒無更新）→ 跳過
        try:
            if time.time() - status_file.stat().st_mtime > 60:
                continue
        except OSError:
            continue

        return True

    return False


def register_pid(pid: int, config: dict) -> None:
    """供 daemon_manager 呼叫，拉起 daemon 後登記 PID。"""
    # TODO: 寫入 registry 檔案
    pass


def _is_process_alive(process_pid: int) -> bool:
    """本機進程存在檢查（Windows：OpenProcess；POSIX：os.kill(pid, 0)）。"""
    if process_pid <= 0:
        return False

    if sys.platform == "win32":
        process_handle = ctypes.windll.kernel32.OpenProcess(0x1000, False, process_pid)
        if not process_handle:
            return False
        ctypes.windll.kernel32.CloseHandle(process_handle)
        return True

    try:
        os.kill(process_pid, 0)
        return True
    except OSError:
        return False

#!/usr/bin/env python3
"""
daemon_registry.py — PID 登記＋跨平台存活檢查。

職責：
- is_daemon_alive(agent_daemon)：供 daemon_wake 呼叫（pid 活＋父程序＝本 bridge＋心跳新鮮＋冇 .closing 標記）
- _is_process_alive(pid)：跨平台 PID 檢查（私有）
- 收工標記：{program}.{pid}.closing（daemon 收工前寫；wake 見到即當死，即刻拉起新 daemon）
"""

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false
# （psutil 冇 type stub——「類型部分未知」一律關，其餘照檢）

import time
from pathlib import Path

import psutil

from .. import context
from ..runtime import process


# 心跳新鮮門檻（秒——超過即當死；daemon 心跳每 1 秒寫一次）
DAEMON_ALIVE_HEARTBEAT_FRESH_SEC = 3.0


def is_daemon_alive(agent_daemon: str) -> bool:
    """檢查 agent daemon 是否生存（{agent_daemon}.{pid}.json——pid 活＋父程序＝本 bridge＋心跳新鮮＋冇收工標記）。"""
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

        # 判定：pid 唔活 → 清殘檔（心跳＋收工標記）後跳過
        if not _is_process_alive(daemon_pid):
            _remove_stale_status_file(daemon_process_file, daemon_pid)

            continue

        # 判定：父程序唔係本 bridge → 唔係自己嗰隻
        if _get_parent_pid(daemon_pid) != process.self_pid():
            continue

        # 判定：有「收緊工」標記 → 當死（正關閉中，唔可以派單）
        if _get_closing_marker_file(agent_daemon, daemon_pid).exists():
            continue

        # 判定：心跳太舊（執行緒卡死／收緊工未寫標記）→ 當死
        if _heartbeat_age_sec(daemon_process_file) > DAEMON_ALIVE_HEARTBEAT_FRESH_SEC:
            continue

        return True

    return False


def _get_closing_marker_file(agent_daemon: str, daemon_pid: int) -> Path:
    """收工標記路徑（{program}.{pid}.closing——daemon 收工前寫落）。"""
    return context.PROCESS_DIR / (agent_daemon + "." + str(daemon_pid) + ".closing")


def _heartbeat_age_sec(daemon_process_file: Path) -> float:
    """心跳檔年齡（秒；讀唔到 → 極大值＝當死）。"""
    try:
        return time.time() - daemon_process_file.stat().st_mtime
    except OSError:
        return float("inf")


def _remove_stale_status_file(daemon_process_file: Path, daemon_pid: int) -> None:
    """清死 pid 殘檔（心跳＋收工標記）——避免目錄累積垃圾。"""
    marker_file = _get_closing_marker_file(daemon_process_file.stem.split(".")[0], daemon_pid)

    for stale_file in (daemon_process_file, marker_file):
        # 判定：檔唔存在 → 下一個
        if stale_file.exists() is False:
            continue

        try:
            stale_file.unlink()
        except OSError:
            continue


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

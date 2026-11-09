#!/usr/bin/env python3
"""
single_instance.py — 同名 daemon 單一實例（先 boot 者勝；可獨立搬走）

對外：
- exit_if_duplicate(program_name)  有更早啟動且仍活嘅同名 daemon → log ＋ 自殺
- find_duplicate(program_name)     掃同名心跳檔 → 回傳更早嘅活 daemon（冇＝None）

判定三條件（全部成立才算重複）：
1. pid 活（process.is_process_running）
2. 心跳新鮮（updated_at ≤ STALE_SEC；過期＝卡死，可以接手）
3. create_time 更早（相同時間比 pid——細者為先）

上游：daemon_manager.initialize()（開機一次）
下游：runtime/process.py（生死）、runtime/heartbeat.py 寫嘅狀態檔（讀取端）
"""

import time
from typing import TypedDict

from .. import context
from bootstrap import sc_logger
from ..utils import io
from . import lifecycle, process

# 同名 daemon 心跳容許幾舊（秒——超過＝對方卡死，可以接手）
STALE_SEC = 10


def exit_if_duplicate(program_name: str) -> None:
    """有更早啟動且仍活嘅同名 daemon → 記 log ＋ 自殺。"""
    duplicate_daemon = find_duplicate(program_name)

    # 判定：冇重複 → 繼續
    if duplicate_daemon is None:
        return

    sc_logger.log("duplicate daemon running pid=" + str(duplicate_daemon["pid"]) + " (older), self kill")
    lifecycle.exit("duplicate daemon running", 0)


class DuplicateDaemon(TypedDict):
    """重複 daemon 身份（pid＋create_time）。"""

    pid: int
    create_time: int


def find_duplicate(program_name: str) -> DuplicateDaemon | None:
    """掃同名心跳檔：回傳比自己更早啟動且仍活嘅 daemon（冇＝None）。"""
    process_status_dir = context.PROCESS_DIR
    self_pid = process.self_pid()
    self_create_time = process.self_create_time()

    for heartbeat_file in process_status_dir.glob(program_name + ".*.json"):
        heartbeat_record = io.read_json(heartbeat_file)

        # 判定：讀唔到（壞檔／消失）→ 跳過
        if heartbeat_record is None:
            sc_logger.log("heartbeat read failed: " + heartbeat_file.name)
            continue

        daemon_pid = int(str(heartbeat_record.get("pid", 0)))

        # 判定：無效 pid 或自己 → 跳過
        if daemon_pid < 1 or daemon_pid == self_pid:
            continue

        # 判定：對方已死 → 跳過（殘檔唔算重複）
        if process.is_process_running(daemon_pid) is False:
            continue

        updated_at = float(str(heartbeat_record.get("updated_at", 0)))

        # 判定：舊檔冇 updated_at → 用檔案 mtime
        if updated_at <= 0:
            updated_at = heartbeat_file.stat().st_mtime

        # 判定：心跳過期（對方卡死）→ 可以接手，唔算重複
        if time.time() - updated_at > STALE_SEC:
            continue

        daemon_create_time = int(str(heartbeat_record.get("create_time", 0)))

        duplicate_daemon: DuplicateDaemon = {"pid": daemon_pid, "create_time": daemon_create_time}

        # 判定：對方更早啟動 → 我係重複
        if daemon_create_time < self_create_time:
            return duplicate_daemon

        # 判定：同一啟動時間 → pid 細者為先
        if daemon_create_time == self_create_time and daemon_pid < self_pid:
            return duplicate_daemon

    return None

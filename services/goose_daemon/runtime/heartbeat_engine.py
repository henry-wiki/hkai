#!/usr/bin/env python3
"""
heartbeat_engine.py — 心跳（引擎專用：子程序狀態檔／11 欄同契約；由 worker 代寫）

與 heartbeat_worker.py 關係：同一契約（11 欄），但對象唔同——本檔寫「引擎（goose 子程序）」嘅狀態檔，
唔係寫自己。引擎係第三方程序，冇自己嘅心跳執行緒，所以：
- 唔起 thread、唔註冊 atexit、唔查母親（探測母親係 worker 嘅事）
- 由持有人（worker）每拍呼叫 update_entry()；引擎退場時 remove_heartbeat_file()

狀態檔：{PROCESS_DIR}/goose.{engine_pid}.json（PHP 程序清單讀）
"""

import os
from pathlib import Path

from .. import context
from ..utils.io import write_json_atomic
from ..worker import identity
from . import process

# 引擎狀態 entry（entry_init 建立；update_entry 只更新時間欄）
_heartbeat_entry: dict[str, object] | None = None

# 心跳次數（單一計數來源——entry 內 beat_count 由呢度寫入）
_beat_count = 0

# 引擎 pid（entry_init 記住；update_entry／remove 用）
_engine_pid = 0


def init(engine_pid: int) -> None:
    """引擎出世一次過登記：台帳一行 ＋ 狀態檔第一筆（同一時刻、單一入口）。"""
    worker_pid = os.getpid()

    process.record_spawn(
        chat_id        ="",
        message_id     ="",
        process_pid    =engine_pid,
        process_ppid   =worker_pid,
        program        =identity.ENGINE_PROGRAM_NAME,
        program_label  =identity.ENGINE_PROGRAM_LABEL,
    )

    entry_init(engine_pid)


def entry_init(engine_pid: int) -> None:
    """建立引擎狀態檔（啟動時間＋第一筆心跳）。"""
    global _heartbeat_entry, _engine_pid, _beat_count

    _engine_pid = engine_pid
    _beat_count = 1

    started_at = context.get_timestamp()
    started_at_fmt = context.strtotime(started_at)

    _heartbeat_entry = {
        "pid":                engine_pid,
        "ppid":               process_parent_pid(),
        "program":            identity.ENGINE_PROGRAM_NAME,
        "program_label":      identity.ENGINE_PROGRAM_LABEL,
        "create_time":        engine_create_time(engine_pid),
        "parent_create_time": process_parent_create_time(),
        "started_at":         started_at,
        "started_at_fmt":     started_at_fmt,
        "updated_at":         started_at,
        "updated_at_fmt":     started_at_fmt,
        "beat_count":         _beat_count,
    }

    _ = write_json_atomic(_get_heartbeat_file(), _heartbeat_entry)


def get_engine_pid() -> int:
    """目前登記引擎 pid（0＝冇引擎）。"""
    return _engine_pid


def get_engine_create_time() -> int:
    """目前登記引擎 create_time（整數毫秒；0＝冇引擎——防 pid 重用）。"""
    # 判定：未登記 → 回 0
    if _heartbeat_entry is None:
        return 0

    engine_create_time = _heartbeat_entry["create_time"]

    # 判定：值係整數 → 回值；否則 0
    if isinstance(engine_create_time, int):
        return engine_create_time

    return 0


def update_entry() -> None:
    """每拍覆寫 updated_at（檔案新鮮＝引擎在跑）。"""
    global _beat_count

    # 判定：未登記 → 唔寫
    if _heartbeat_entry is None:
        return

    updated_at = context.get_timestamp()
    _heartbeat_entry["updated_at"]     = updated_at
    _heartbeat_entry["updated_at_fmt"] = context.strtotime(updated_at)
    _beat_count = _beat_count + 1
    _heartbeat_entry["beat_count"]     = _beat_count

    _ = write_json_atomic(_get_heartbeat_file(), _heartbeat_entry)


def remove_heartbeat_file() -> None:
    """刪引擎狀態檔（引擎退場——唔留殘檔）。"""
    global _heartbeat_entry, _engine_pid

    heartbeat_file = _get_heartbeat_file()

    _heartbeat_entry = None
    _engine_pid = 0

    # 判定：檔案唔存在 → 無事可做
    if heartbeat_file.exists() is False:
        return

    try:
        heartbeat_file.unlink()
    except OSError:
        pass


def process_parent_pid() -> int:
    """引擎嘅母親＝worker（本程序）pid。"""
    return os.getpid()


def process_parent_create_time() -> int:
    """本程序（worker）create_time（整數毫秒）。"""
    return process.self_create_time()


def engine_create_time(engine_pid: int) -> int:
    """引擎 create_time（整數毫秒——建檔一刻讀，引擎必在）。"""
    return process.get_process_create_time(engine_pid) or 0


def _get_heartbeat_file() -> Path:
    """引擎狀態檔路徑（goose.{pid}.json）。"""
    return Path(context.PROCESS_DIR) / (identity.ENGINE_PROGRAM_NAME + "." + str(_engine_pid) + ".json")

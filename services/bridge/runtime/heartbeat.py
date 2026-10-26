#!/usr/bin/env python3
"""
heartbeat.py — 心跳（狀態檔＋生存證明＋閒置自殺；bridge 專用，可獨立搬走）

對外入口：
- init()        建立自己狀態檔 → 清在線標記 → 起背景執行緒
                  ① 每 HEARTBEAT_INTERVAL_SEC 寫一次狀態檔（唔會停）
                  ② 每次跳都對比在線檔年齡——超過 IDLE_EXIT_SEC 且冇進行中串流 → 自殺
- start()       起背景心跳執行緒（已喺跑 → 唔重複開）
- stop(reason)  停執行緒 → lifecycle.exit(reason)
- exception_kill()  例外退出（atexit）：記 log → stop

狀態檔：{PROCESS_DIR}/{PROGRAM_NAME}.{pid}.json（PHP 程序清單讀）
在線時間交 runtime/presence.py（ping 時刷新）。
"""

# heartbeat ↔ lifecycle 互相引用（lifecycle 內「延遲 import 免循環」係刻意）——靜態掃描會標成 import cycle，故本檔停用該規則（檔案級）。
# pyright: reportImportCycles=false

import atexit
import threading
import time
from pathlib import Path

from ..context import (
    HEARTBEAT_INTERVAL_SEC,
    IDLE_EXIT_SEC,
    PROCESS_DIR,
    PROGRAM_LABEL,
    PROGRAM_NAME,
    get_timestamp,
    strtotime,
)
from ..server import server_stream
from ..utils.io import write_json_atomic
from . import lifecycle, presence, process

# 本程序狀態（entry_init 建立；update_entry 只更新時間欄）
_heartbeat_entry = None

# 背景執行緒（start 建立、stop 收掉）
_heartbeat_thread = None

# 停止旗：stop() 用 set() 通知收工；心跳執行緒每輪用 is_set() 檢查（True＝要走）
_stop_requested = threading.Event()


# ===== 對外入口 =====

def init() -> None:
    """初始化心跳：建立狀態檔＋清在線標記 → 起背景執行緒。"""
    entry_init()

    # 開機清在線標記（上一條生命週期殘留嘅 mtime 唔可以當成本次有人連住）
    presence.stop()

    # 由開機一刻起計（檔案唔存在＝inf 會即刻自殺——所以開機即 touch 歸零）
    presence.touch()

    start()


def start() -> None:
    """起背景心跳執行緒（已喺跑 → 唔重複開）。"""
    global _heartbeat_thread

    # 判定：已有心跳執行緒喺跑 → 唔重複開（避免多條同時寫同一個狀態檔）
    if _heartbeat_thread is not None and _heartbeat_thread.is_alive():
        return

    # 重設停止旗（上次 stop 設過）——否則新執行緒第一輪即退出（開即死）
    _stop_requested.clear()

    _heartbeat_thread = threading.Thread(
        target      =_run_heartbeat,
        name        ="heartbeat",
        daemon      =True,
    )
    _heartbeat_thread.start()


def stop(reason: str = "") -> None:
    """停止心跳並結束程序：停執行緒 → lifecycle.exit(reason)（清狀態／在線檔＋死亡）。"""
    global _heartbeat_thread

    # 通知心跳執行緒收工（佢下一輪檢查就跳出——唔係即時中斷）
    _stop_requested.set()

    if _heartbeat_thread is not None:
        # 判定：喺心跳執行緒內叫 stop（閒置自殺）→ 唔可以 join 自己
        if threading.current_thread() is not _heartbeat_thread:
            _heartbeat_thread.join(timeout=2.0)

        _heartbeat_thread = None

    # 收工（lifecycle.exit 會清狀態／在線檔再死）
    lifecycle.exit(reason)


def exception_kill() -> None:
    """例外退出（atexit）：記 log → 停心跳（stop 內會刪狀態檔）。"""
    # 記 log（非 os._exit 收工——留痕）
    try:
        from . import sc_logger

        sc_logger.log("exit: unexpected interpreter exit (exception_kill)")
    except Exception:  # noqa: BLE001, S110
        pass

    stop("unexpected interpreter exit")


# ===== 狀態檔 =====

def entry_init() -> None:
    """建立自己狀態檔（啟動時間＋第一筆心跳）。"""
    global _heartbeat_entry

    started_at = get_timestamp()
    started_at_fmt = strtotime(started_at)

    _heartbeat_entry = {
        "pid":               process.self_pid(),
        "ppid":              process.mother_pid(),
        "program":           PROGRAM_NAME,
        "program_label":     PROGRAM_LABEL,
        "create_time":       process.self_create_time(),
        "parent_create_time": process.parent_create_time(),
        "started_at":        started_at,
        "started_at_fmt":    started_at_fmt,
        "updated_at":        started_at,
        "updated_at_fmt":    started_at_fmt,
    }

    _ = write_json_atomic(_get_heartbeat_file(), _heartbeat_entry)


def update_entry() -> None:
    """每秒覆寫 updated_at（檔案新鮮＝在跑）。"""
    global _heartbeat_entry

    # 判定：未登記（程式收工中）→ 唔寫
    if _heartbeat_entry is None:
        return

    updated_at = get_timestamp()
    _heartbeat_entry["updated_at"]     = updated_at
    _heartbeat_entry["updated_at_fmt"] = strtotime(updated_at)

    _ = write_json_atomic(_get_heartbeat_file(), _heartbeat_entry)


def remove_heartbeat_file() -> None:
    """刪自己狀態檔（程序結束——唔留殘檔）。"""
    global _heartbeat_entry

    _heartbeat_entry = None

    heartbeat_file = _get_heartbeat_file()

    # 判定：檔案唔存在 → 無事可做
    if heartbeat_file.exists() is False:
        return

    try:
        heartbeat_file.unlink()
    except OSError:
        pass


# ===== 內部 =====

def _run_heartbeat() -> None:
    """背景心跳：每輪寫狀態檔；有連線＝唔計閒置，冇連線＝計閒置（過期自殺）。"""
    # 每輪先檢查停止旗：True（有人叫收工）→ 跳出迴圈，執行緒結束
    while _stop_requested.is_set() is False:
        # 每輪第一件事：寫心跳（生存證明——無論忙唔忙都要寫）
        update_entry()

        # 母親監視：母親唔在 → 只自殺（子孫自己 3 秒內自殺）
        lifecycle.exit_when_mother_gone()

        # 判定：有輸出串流（有人正在收即時輸出）→ 唔計閒置（等下一輪）
        if server_stream.get_active_stream_count() > 0:
            time.sleep(HEARTBEAT_INTERVAL_SEC)
            continue

        # 唯一條件：最後一次有頁面連住距今幾久（presence.py 喺 ping／連線時刷新）
        presence_idle_sec = presence.age_sec()

        # 判定：冇任何連線超過上限 → 自殺
        if presence_idle_sec > IDLE_EXIT_SEC:
            stop("stopped from heartbeat: presence_idle_sec=" + format(presence_idle_sec, ".0f"))

        time.sleep(HEARTBEAT_INTERVAL_SEC)


def _get_heartbeat_file() -> Path:
    """自己狀態檔路徑（{PROGRAM_NAME}.{pid}.json）。"""
    return Path(PROCESS_DIR) / (PROGRAM_NAME + "." + str(process.self_pid()) + ".json")


_ = atexit.register(exception_kill)

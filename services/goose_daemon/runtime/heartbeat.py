#!/usr/bin/env python3
"""
heartbeat.py — 心跳（daemon 主程序用：狀態檔＋生存證明＋母親監視；可獨立搬走）

與 heartbeat_worker.py 關係：同一契約（11 欄），但分開兩份——daemon 用本檔，worker 用 heartbeat_worker.py。

對外入口（同 bridge 一致）：
- init()        建立自己狀態檔 → 起背景執行緒
                  每 HEARTBEAT_INTERVAL_SEC 寫一次狀態檔（唔會停）
                  每拍檢查母親（bridge）——唔在即自殺
- start()       起背景心跳執行緒（已喺跑 → 唔重複開）
- stop(reason)  停執行緒 → lifecycle.exit(reason)
- exception_kill()  例外退出（atexit）：記 log → stop

狀態檔：{PROCESS_DIR}/{PROGRAM_NAME}.{pid}.json（PHP 程序清單讀）
身份（PROGRAM_NAME／PROGRAM_LABEL）取自 context（源頭 identity.DAEMON_*——固定，不運行中改寫）。
"""

import atexit
import contextlib
import os
import threading
import time
from pathlib import Path

from .. import context
from ..utils.io import write_json_atomic
from . import lifecycle, process

# 本程序狀態（entry_init 建立；update_entry 只更新時間欄）
_heartbeat_entry: dict[str, object] | None = None

# 心跳次數（單一計數來源——entry 內 beat_count 由呢度寫入）
_beat_count = 0

# 背景執行緒（start 建立、stop 收掉）
_heartbeat_thread = None

# 停止旗：stop() 用 set() 通知收工；心跳執行緒每輪用 is_set() 檢查（True＝要走）
_stop_requested = threading.Event()

# ===== 對外入口 =====

def init() -> None:
    """初始化心跳：台帳一行 → 建立狀態檔 → 起背景執行緒。"""
    process.record_spawn(
        chat_id        ="",
        message_id     ="",
        process_pid    =os.getpid(),
        process_ppid   =os.getppid(),
        program        =context.PROGRAM_NAME,
        program_label  =context.PROGRAM_LABEL,
    )

    entry_init()
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
    """停止心跳並結束程序：停執行緒 → lifecycle.exit(reason)（清狀態檔＋死亡）。"""
    # 停心跳執行緒（唔停 → 之後收尾刪檔會被佢重寫，殘檔復活）
    _stop_heartbeat_thread()

    # 收工（lifecycle.exit 會清狀態檔再死）
    lifecycle.exit(reason)

def exception_kill() -> None:
    """例外退出（atexit）：記 log → 停心跳（stop 內會刪狀態檔）。"""
    # 記 log（非 os._exit 收工——留痕）
    try:
        from bootstrap import sc_logger

        sc_logger.log("exit: unexpected interpreter exit (exception_kill)")
    except Exception:  # noqa: BLE001, S110
        pass

    stop("unexpected interpreter exit")

# ===== 狀態檔 =====

def entry_init() -> None:
    """建立自己狀態檔（啟動時間＋第一筆心跳）。"""
    global _heartbeat_entry, _beat_count

    _beat_count = 1

    started_at = context.get_timestamp()
    started_at_fmt = context.strtotime(started_at)

    _heartbeat_entry = {
        "pid":               process.self_pid(),
        "ppid":              process.mother_pid(),
        "program":           context.PROGRAM_NAME,
        "program_label":     context.PROGRAM_LABEL,
        "create_time":       process.self_create_time(),
        "parent_create_time": process.parent_create_time(),
        "started_at":        started_at,
        "started_at_fmt":    started_at_fmt,
        "updated_at":        started_at,
        "updated_at_fmt":    started_at_fmt,
        "beat_count":        _beat_count,
    }

    _ = write_json_atomic(_get_heartbeat_file(), _heartbeat_entry)

def update_entry() -> None:
    """每拍覆寫 updated_at（檔案新鮮＝在跑）。"""
    global _beat_count

    # 判定：未登記（程式收工中）→ 唔寫
    if _heartbeat_entry is None:
        return

    updated_at = context.get_timestamp()
    _heartbeat_entry["updated_at"]     = updated_at
    _heartbeat_entry["updated_at_fmt"] = context.strtotime(updated_at)

    _beat_count = _beat_count + 1
    _heartbeat_entry["beat_count"]     = _beat_count

    _ = write_json_atomic(_get_heartbeat_file(), _heartbeat_entry)

def write_closing_marker() -> None:
    """寫「收緊工」標記（{PROGRAM_NAME}.{pid}.closing）——外界見到即當本程序已不可用。"""
    closing_file = get_closing_marker_file()

    with contextlib.suppress(OSError):
        _ = closing_file.write_text(str(int(time.time())), encoding="utf-8")

def remove_closing_marker() -> None:
    """刪「收緊工」標記（正常退出路徑——同心跳檔一齊清）。"""
    closing_file = get_closing_marker_file()

    # 判定：檔唔存在 → 無事可做
    if closing_file.exists() is False:
        return

    with contextlib.suppress(OSError):
        _ = closing_file.unlink()

def get_closing_marker_file() -> Path:
    """自己「收緊工」標記路徑（{PROGRAM_NAME}.{pid}.closing）。"""
    return Path(context.PROCESS_DIR) / (context.PROGRAM_NAME + "." + str(process.self_pid()) + ".closing")

def remove_heartbeat_file() -> None:
    """刪自己狀態檔（程序結束——唔留殘檔）＋清「收緊工」標記。"""
    global _heartbeat_entry

    # 先停心跳執行緒（唔停 → 刪完佢再寫，殘檔復活）
    _stop_heartbeat_thread()

    _heartbeat_entry = None

    remove_closing_marker()

    heartbeat_file = _get_heartbeat_file()

    # 判定：檔案唔存在 → 無事可做
    if heartbeat_file.exists() is False:
        return

    with contextlib.suppress(OSError):
        _ = heartbeat_file.unlink()

# ===== 內部 =====

def _exit_if_mother_gone() -> None:
    """母親唔在 → 收場（只在本程序依賴母親時檢查）。"""
    # 判定：本程序唔依賴母親 → 唔檢查
    if context.NEED_MOTHER is not True:
        return

    # 判定：母親唔在 → 收場（經理協議：通知工人撤退 → 清場 → 自殺）
    if process.mother_is_gone():
        lifecycle.exit("mother gone")

def _run_heartbeat() -> None:
    """背景心跳：每拍寫狀態檔；母親唔在 → 自殺。"""
    # 每輪先檢查停止旗：True（有人叫收工）→ 跳出迴圈，執行緒結束
    while _stop_requested.is_set() is False:
        # 每輪第一件事：寫心跳（生存證明——無論忙唔忙都要寫）
        update_entry()

        # 母親監視：母親唔在 → 收場（helper 內先判依賴性）
        _exit_if_mother_gone()

        time.sleep(context.HEARTBEAT_INTERVAL_SEC)

def _stop_heartbeat_thread() -> None:
    """停心跳執行緒（設旗標 → 等佢跳出 → 清引用；自己係心跳執行緒就唔 join）。"""
    global _heartbeat_thread

    _stop_requested.set()
    heartbeat_thread = _heartbeat_thread
    _heartbeat_thread = None

    # 判定：冇執行緒 → 完
    if heartbeat_thread is None:
        return

    # 判定：呼叫者就係心跳執行緒 → 唔可以 join 自己
    if threading.current_thread() is heartbeat_thread:
        return

    heartbeat_thread.join(timeout=2.0)

def _get_heartbeat_file() -> Path:
    """自己狀態檔路徑（{PROGRAM_NAME}.{pid}.json）。"""
    return Path(context.PROCESS_DIR) / (context.PROGRAM_NAME + "." + str(process.self_pid()) + ".json")

_ = atexit.register(exception_kill)

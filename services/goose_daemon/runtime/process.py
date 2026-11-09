#!/usr/bin/env python3
"""
process.py — 程序身份＋程序台帳（daemon 專用；可獨立搬走）

身份（啟動即記）：
- init()                 啟動即記：自己 pid ＋ 母親 pid＋建立時間
- self_pid()             本程序 pid
- mother_pid()           母親 pid（記錄值；0＝冇母親）
- get_live_mother_pid()  母親仍在 → pid；已死／被重用 → 0
- mother_is_gone()       母親唔在／被重用 → True
- self_create_time()     自己程序建立時間
- parent_create_time()   母親（父）程序建立時間
- is_process_running()   指定 pid 係唔係仍在跑（psutil 實查）

台帳（append-only JSONL——每個被拉起嘅程序一行）：
- 路徑固定：context.PROCESS_FILE_LIST
- record_spawn()             記程序出世（一個入口——6 欄全部由呼叫端帶；本檔唔推斷、唔恢復）

責任：只做記錄同感應，唔殺程序（殺交 lifecycle.exit／exit_children）
"""

import json
import os
import time
from pathlib import Path
from typing import cast

import psutil

from .. import context
from bootstrap import sc_logger

# 本程序 pid（init 時記錄）
_self_pid = 0

# 母親 pid（init 時記錄；0＝冇母親）
_mother_pid = 0

# 母親建立時間（防 pid 重用——記錄時母親仍然在世才有值）
_mother_create_time = None

# 台帳鎖檔名（record_spawn 同清潔共用同一把鎖）
_LIST_LOCK_FILENAME = "process_list.lock"

# 鎖過期（秒——殘鎖：持有人已死）
_LIST_LOCK_STALE_SEC = 10.0


def init() -> None:
    """啟動即記：自己 pid ＋ 母親 pid＋建立時間。"""
    global _self_pid, _mother_pid, _mother_create_time

    _self_pid = os.getpid()

    # 母親＝本程序嘅 OS 父（拉起一刻最準）
    _mother_pid = os.getppid()

    # 判定：ppid ≤ 1（POSIX 已被 reparent 去 init）→ 當冇母親
    if _mother_pid <= 1:
        _mother_pid = 0
        _mother_create_time = None

        return

    _mother_create_time = get_process_create_time(_mother_pid)


def is_process_running(process_pid: int) -> bool:
    """指定 pid 嘅程序係唔係仍在跑（psutil 實查——唔靠 create_time 快取）。"""
    # 判定：pid 無效（0 喺 Windows 係 Idle Process）→ 當唔在跑
    if int(process_pid) < 1:
        return False

    try:
        return psutil.Process(int(process_pid)).is_running()
    except psutil.Error:
        return False


def self_pid() -> int:
    """本程序 pid。"""
    return _self_pid


def get_process_create_time(process_pid: int) -> "int | None":
    """讀程序建立時間（整數毫秒；讀唔到 → None）。

    用整數毫秒：浮點尾位跨語言／JSON 序列化會漂移，整數先可以精確 == 對比。
    """
    try:
        create_time = cast("float", psutil.Process(process_pid).create_time())
    except psutil.Error as exc:
        sc_logger.log("create_time read failed pid=" + str(process_pid) + " | " + str(exc))
        return None

    # 轉整數毫秒（截斷——寫入同比對用同一個公式）
    return int(create_time * 1000)


def self_create_time() -> int:
    """自己程序建立時間（整數毫秒；讀唔到 → 0）。"""
    return get_process_create_time(_self_pid) or 0


def parent_create_time() -> int:
    """母親（父）程序建立時間（整數毫秒；未記錄／讀唔到 → 0）。"""
    return _mother_create_time or 0


def mother_pid() -> int:
    """母親 pid（0＝冇母親）。"""
    return _mother_pid


def get_live_mother_pid() -> int:
    """母親仍在 → pid；已死／被重用 → 0。"""
    # 判定：母親已死或號碼被重用 → 回 0
    if mother_is_gone() is True:
        return 0

    return _mother_pid


def mother_is_gone() -> bool:
    """母親唔在／被重用 → True（冇母親 → False）。"""
    # 判定：冇母親 pid → 唔判斷
    if _mother_pid <= 0:
        return False

    # 判定：母親程序唔存在 → 母親已死
    try:
        _ = psutil.Process(_mother_pid)
    except psutil.Error:
        return True

    # 判定：未記錄建立時間 → 只能信 pid（少數情況）
    if _mother_create_time is None:
        return False

    # 判定：pid 存在但建立時間唔同 → 原母親已死（號碼被重用）
    current_create_time = get_process_create_time(_mother_pid)

    if current_create_time is None:
        return True

    return current_create_time != _mother_create_time


# ===== 程序台帳（append-only JSONL） =====

def record_spawn(
    chat_id: str,
    message_id: str,
    process_pid: int,
    process_ppid: int,
    program: str,
    program_label: str,
) -> None:
    """記程序出世（單一入口——全部由呼叫端帶，本檔唔推斷、唔恢復）。

    - 自己程序：呼叫端帶自己 pid／ppid
    - 外部程序（goose）：呼叫端帶該程序 pid／ppid
    """
    # 同一把鎖：清潔死行＋append 自己（拿唔到鎖＝唔清潔、照 append）
    list_lock_path = _acquire_list_lock()

    if list_lock_path is not None:
        _sweep_dead_lines_locked()

    _append_ledger_line(process_pid, process_ppid, chat_id, message_id, program, program_label)

    if list_lock_path is not None:
        _release_list_lock(list_lock_path)


def sweep_dead_lines(dead_pid: int = 0) -> None:
    """清潔台帳：剔走死程序行（自殺前／正常結束時叫——拿唔到鎖＝跳過）。

    dead_pid：即將死亡嘅自己（>0＝唔理心跳、直接當死，連自己嗰行一齊清）。
    """
    list_lock_path = _acquire_list_lock()

    # 判定：拿唔到鎖（有其他程序喺改）→ 跳過
    if list_lock_path is None:
        return

    try:
        _sweep_dead_lines_locked(dead_pid)
    finally:
        _release_list_lock(list_lock_path)


def _sweep_dead_lines_locked(dead_pid: int = 0) -> None:
    """清潔本體（呼叫者持鎖）：讀原檔 → 剔死行 → 原子替換（冇死行＝零寫入）。"""
    list_file = context.PROCESS_FILE_LIST

    # 判定：檔案未存在 → 無事可做
    if list_file.is_file() is False:
        return

    kept_lines: list[str] = []
    removed_count = 0

    # 逐行判活（壞行／死行剔走）
    for list_line in list_file.read_text(encoding="utf-8").splitlines():
        list_line = list_line.strip()

        # 判定：空行 → 跳過
        if list_line == "":
            continue

        ledger_identity = _read_ledger_identity(list_line)

        # 判定：壞行（讀唔到 pid）→ 剔走
        if ledger_identity is None:
            removed_count = removed_count + 1
            continue

        recorded_pid = ledger_identity[0]
        recorded_create_time = ledger_identity[1]

        # 判定：即將死亡嘅自己 → 當死
        if dead_pid > 0 and recorded_pid == dead_pid:
            removed_count = removed_count + 1
            continue

        # 判定：程序仍在 → 保留
        if _is_record_alive(recorded_pid, recorded_create_time) is True:
            kept_lines.append(list_line)
            continue

        removed_count = removed_count + 1

    # 判定：冇死行 → 零寫入（唔動檔）
    if removed_count == 0:
        return

    temporary_file = list_file.parent / (list_file.name + ".sweep.tmp")
    _ = temporary_file.write_text(chr(10).join(kept_lines) + chr(10), encoding="utf-8")
    os.replace(temporary_file, list_file)


def _read_ledger_identity(list_line: str):
    """解析台帳行 → (pid, create_time)；壞行／冇有效 pid → None。"""
    try:
        record = cast("object", json.loads(list_line))
    except json.JSONDecodeError:
        return None

    # 判定：非物件（壞行）→ None
    if isinstance(record, dict) is False:
        return None

    record_object: dict[str, object] = cast("dict[str, object]", record)
    raw_pid = record_object.get("pid", 0)

    # 判定：pid 非整數 → None；整數就照用
    if isinstance(raw_pid, int):
        recorded_pid = raw_pid
    else:
        return None

    # 判定：pid 無效 → None
    if recorded_pid <= 0:
        return None

    raw_create_time = record_object.get("create_time")

    # 判定：create_time 係整數 → 回值；否則當舊行（只靠 pid）
    if isinstance(raw_create_time, int):
        return (recorded_pid, raw_create_time)

    return (recorded_pid, None)


def _is_record_alive(recorded_pid: int, recorded_create_time: int | None) -> bool:
    """台帳行對應程序仍在？（create_time 精確比對——防 pid 重用）。"""
    current_create_time = get_process_create_time(recorded_pid)

    # 判定：讀唔到（程序已死）→ 死
    if current_create_time is None:
        return False

    # 判定：行冇 create_time（舊行）→ 只靠 pid 存在
    if recorded_create_time is None:
        return True

    return current_create_time == recorded_create_time


def _acquire_list_lock() -> "Path | None":
    """拿台帳鎖（O_EXCL——跨平台；殘鎖過期即清再試一次；拿唔到回 None）。"""
    lock_path = context.PROCESS_FILE_LIST.parent / _LIST_LOCK_FILENAME
    lock_path.parent.mkdir(parents=True, exist_ok=True)

    try:
        lock_fd = os.open(str(lock_path), os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        os.close(lock_fd)
        return lock_path
    except FileExistsError:
        pass

    # 判定：殘鎖過期（持有人已死）→ 清走再試
    try:
        lock_age_sec = time.time() - lock_path.stat().st_mtime
    except OSError:
        return None

    # 判定：未過期 → 放棄
    if lock_age_sec < _LIST_LOCK_STALE_SEC:
        return None

    try:
        lock_path.unlink()
        lock_fd = os.open(str(lock_path), os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        os.close(lock_fd)
        return lock_path
    except OSError:
        return None


def _release_list_lock(lock_path: Path) -> None:
    """釋放台帳鎖（唔存在＝略過）。"""
    try:
        lock_path.unlink()
    except OSError:
        return


def _append_ledger_line(pid: int, ppid: int, chat_id: str | None, message_id: str | None, program: str, program_label: str) -> None:
    """append 一行台帳（唯一寫入點——schema 單一來源；失敗只記 log）。"""
    try:
        record = {}
        record["ts"]                 = context.get_datetime()
        record["create_time"]        = get_process_create_time(pid)
        record["parent_create_time"] = get_process_create_time(ppid)
        record["event"]              = "spawn"
        record["pid"]                = int(pid)
        record["ppid"]               = int(ppid)
        record["program"]            = program
        record["program_label"]      = program_label
        record["chat_id"]            = "" if chat_id is None else str(chat_id)
        record["message_id"]         = "" if message_id is None else str(message_id)

        # 目錄不存在先建立（首次啟動——檔案尚不存在）
        context.PROCESS_FILE_LIST.parent.mkdir(parents=True, exist_ok=True)

        with open(context.PROCESS_FILE_LIST, "a", encoding="utf-8", newline="\n") as list_file:
            _ = list_file.write(json.dumps(record, ensure_ascii=False) + "\n")
    except Exception as exc:  # noqa: BLE001
        sc_logger.log(f"process list write failed: {exc}")



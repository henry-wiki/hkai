#!/usr/bin/env python3
"""
process.py — 程序身份＋程序台帳（每個程序自己一份；啟動即記自己同母親）

責任：只做記錄同感應，唔殺程序（殺交 lifecycle.exit／exit_children）

三支：
- init()            啟動即記：自己 pid ＋ 母親 pid＋建立時間
- self_pid()        本程序 pid
- mother_pid()      母親 pid（記錄值；0＝冇母親）
- get_live_mother_pid()  母親仍在 → pid；已死／被重用 → 0
- mother_is_gone()  母親唔在／被重用 → True
"""

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false
# （psutil 冇 type stub——「類型部分未知」一律關，其餘照檢）

import json
import os
from pathlib import Path

import psutil

from ..context import PROCESS_FILE_LIST, PROGRAM_LABEL, PROGRAM_NAME, get_datetime
from . import sc_logger

# 本程序 pid（init 時記錄）
_self_pid = 0

# 母親 pid（init 時記錄；0＝冇母親）
_mother_pid = 0

# 母親建立時間（防 pid 重用——記錄時母親仍然在世才有值）
_mother_create_time = None


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

    _mother_create_time = _get_process_create_time(_mother_pid)


def self_pid() -> int:
    """本程序 pid。"""
    return _self_pid


def _get_process_create_time(process_pid: int) -> "int | None":
    """讀程序建立時間（整數毫秒；讀唔到 → None）。

    用整數毫秒：浮點尾位跨語言／JSON 序列化會漂移，整數先可以精確 == 對比。
    """
    try:
        create_time = psutil.Process(process_pid).create_time()
    except psutil.Error as exc:
        sc_logger.log("create_time read failed pid=" + str(process_pid) + " | " + str(exc))
        return None

    # 轉整數毫秒（截斷——寫入同比對用同一個公式）
    return int(create_time * 1000)


def self_create_time() -> int:
    """自己程序建立時間（整數毫秒；讀唔到 → 0）。"""
    return _get_process_create_time(_self_pid) or 0


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

    # 判定：pid 唔存在 → 母親已死
    if psutil.pid_exists(_mother_pid) is False:
        return True

    # 判定：未記錄建立時間 → 只能信 pid（少數情況）
    if _mother_create_time is None:
        return False

    # 判定：pid 存在但建立時間唔同 → 原母親已死（號碼被重用）
    current_create_time = _get_process_create_time(_mother_pid)

    if current_create_time is None:
        return True

    return current_create_time != _mother_create_time

# ===== 程序台帳（append-only JSONL——bridge 只有一條常駐程序；只 append、唔清潔）=====

def get_list_path() -> Path:
    """取得程序清單檔案路徑（固定：context.PROCESS_FILE_LIST）。"""
    return PROCESS_FILE_LIST


def get_create_time(pid: int) -> int:
    """程序建立時間（epoch 毫秒 int；讀唔到 → 0）。"""
    try:
        return int(psutil.Process(int(pid)).create_time() * 1000)
    except psutil.Error:
        return 0


def append_event(event: str, pid: int, ppid: int, chat_id: str | None = "", message_id: str | None = "") -> None:
    """append 一行程序清單（永不改寫、永不刪行；失敗只記 log）。"""
    try:
        list_path = get_list_path()

        record = {}
        record["ts"]            = get_datetime()
        record["create_time"]   = get_create_time(pid)
        record["event"]         = event
        record["pid"]           = int(pid)
        record["ppid"]          = int(ppid)
        record["program"]       = PROGRAM_NAME
        record["program_label"] = PROGRAM_LABEL
        record["chat_id"]       = "" if chat_id is None else str(chat_id)
        record["message_id"]    = "" if message_id is None else str(message_id)

        # 目錄不存在先建立（首次啟動——檔案尚不存在）
        list_path.parent.mkdir(parents=True, exist_ok=True)

        with open(list_path, "a", encoding="utf-8", newline="\n") as list_file:
            _ = list_file.write(json.dumps(record, ensure_ascii=False) + "\n")

    except Exception as exc:  # noqa: BLE001
        sc_logger.log(f"process list write failed: {exc}")


def append_spawn(pid: int, ppid: "int | None" = None, chat_id: str = "", message_id: str = "") -> None:
    """append 一筆 spawn（ppid 預設＝本程序——呼叫者就是父程序）。"""
    append_event(
        event="spawn",
        pid=pid,
        ppid=self_pid() if ppid is None else ppid,
        chat_id=chat_id,
        message_id=message_id,
    )

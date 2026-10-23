#!/usr/bin/env python3
"""
process_list.py

程序程序清單（append-only JSONL）——每一個被拉起的程序（bridge／aider_daemon／worker／aider）各 append 一行。

檔案：data/process_status/process_list.jsonl
      路徑＝context.PROCESS_FILE_LIST（站台根 data/process_status/process_list.jsonl——核心路徑，唔靠 runtime.json）；
      與 aider_daemon 的同名模組同格式（兩套件各自持有自己的 runtime 基礎設施）。

一行欄位：
    ts             寫入時間（Y-m-d H:M:S）
    create_time    程序建立時間（epoch；psutil 讀）
    event          spawn／exit／kill（本階段只寫 spawn）
    program        哪一類程序（bridge／aider_daemon／worker／aider）
    pid            該程序 PID
    ppid           拉起它的程序 PID（呼叫者＝父程序；本程序自記時為自己的父程序）
    program_label  程式／模組名（如 bridge.main）
    chat_id／message_id  來源對話／訊息（非訊息層為空）

寫入策略：append ＋ 逐行 JSON（一行 < 4KB，多程序同時寫不會交錯）；
          任何失敗只記 log——程序清單問題絕不影響主流程。
"""

import json
from datetime import datetime
from pathlib import Path

import psutil

from ..context import PROCESS_FILE_LIST, PROGRAM_LABEL, PROGRAM_NAME
from . import process

# 時間格式（與 daemon 的 main_started_at_fmt 一致——同一套呈現）
TIME_FORMAT = "%Y-%m-%d %H:%M:%S"
def get_list_path() -> Path:
    """取得程序清單檔案路徑（固定：context.PROCESS_FILE_LIST）。"""
    return PROCESS_FILE_LIST


def get_create_time(pid: int) -> float:
    """程序建立時間（讀唔到 → 0——例如權限不足／已死）。"""
    try:
        return psutil.Process(int(pid)).create_time()
    except psutil.Error:
        return 0.0


def append_event(event: str, pid: int, ppid: int, chat_id: str = "", message_id: str = "") -> None:
    """append 一行程序清單（永不改寫、永不刪行；失敗只記 log）。"""
    from . import sc_logger

    try:
        list_path = get_list_path()

        record = {}
        record["ts"]            = datetime.now().strftime(TIME_FORMAT)
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
            list_file.write(json.dumps(record, ensure_ascii=False) + "\n")

    except Exception as exc:  # noqa: BLE001
        sc_logger.log(f"process list write failed: {exc}")


def append_spawn(pid: int, ppid: "int | None" = None, chat_id: str = "", message_id: str = "") -> None:
    """append 一筆 spawn（ppid 預設＝本程序——呼叫者就是父程序）。"""
    append_event(
        event="spawn",
        pid=pid,
        ppid=process.self_pid() if ppid is None else ppid,
        chat_id=chat_id,
        message_id=message_id,
    )

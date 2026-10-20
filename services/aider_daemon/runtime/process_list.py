#!/usr/bin/env python3
"""
process_list.py

程序程序清單（append-only JSONL）——每一個被拉起的程序（bridge／aider_daemon／worker／aider）各 append 一行。

檔案：storage_dir/process_status/process_list.jsonl
      檔名出自靜態 SSOT sc_config/hkai/hkai_config.json 的 filenames.process_list；
      路徑由 daemon_manager._apply_static_configs 展開為 process_list_path（storage_dir 拼接）。

一行欄位：
    ts           寫入時間（Y-m-d H:M:S）
    event        spawn／exit／kill（本階段只寫 spawn）
    name         哪一類程序（bridge／aider_daemon／worker／aider）
    pid          該程序 PID
    ppid         拉起它的程序 PID（呼叫者＝父程序；daemon 自記時為自己的父程序）
    program      程式／模組名（如 aider_daemon.main）
    chat_id／message_id  來源對話／訊息（非訊息層為空）

寫入策略：append ＋ 逐行 JSON（一行 < 4KB，多程序同時寫不會交錯）；
          任何失敗只記 log——程序清單問題絕不影響主流程。
"""

import json
import os
from datetime import datetime
from pathlib import Path

# 時間格式（與 daemon 的 main_started_at_fmt 一致——同一套呈現）
TIME_FORMAT = "%Y-%m-%d %H:%M:%S"


def get_list_path(daemon_conf_data: dict) -> "Path | None":
    """取得程序清單路徑（runtime spec 的 process_list_path——未展開回 None）。"""
    list_path = daemon_conf_data.get("process_list_path", "")
    if not list_path:
        return None

    return Path(list_path)


def append_event(daemon_conf_data: dict, event: str, name: str, program: str, pid: int, ppid: int, chat_id: str = "", message_id: str = "") -> None:
    """append 一行程序清單（永不改寫、永不刪行；失敗只記 log）。"""
    from . import sc_logger

    try:
        list_path = get_list_path(daemon_conf_data)
        if list_path is None:
            return

        record = {}
        record["ts"]         = datetime.now().strftime(TIME_FORMAT)
        record["event"]      = event
        record["name"]       = name
        record["pid"]        = int(pid)
        record["ppid"]       = int(ppid)
        record["program"]    = program
        record["chat_id"]    = "" if chat_id is None else str(chat_id)
        record["message_id"] = "" if message_id is None else str(message_id)

        # 目錄不存在先建立（首次啟動——檔案尚不存在）
        list_path.parent.mkdir(parents=True, exist_ok=True)

        with open(list_path, "a", encoding="utf-8", newline="\n") as list_file:
            list_file.write(json.dumps(record, ensure_ascii=False) + "\n")
    except Exception as exc:  # noqa: BLE001
        sc_logger.log(f"process list write failed: {exc}")


def append_spawn(daemon_conf_data: dict, name: str, program: str, pid: int, ppid: "int | None" = None, chat_id: str = "", message_id: str = "") -> None:
    """append 一筆 spawn（ppid 預設＝本程序——呼叫者就是父程序）。"""
    append_event(
        daemon_conf_data,
        event="spawn",
        name=name,
        program=program,
        pid=pid,
        ppid=os.getpid() if ppid is None else ppid,
        chat_id=chat_id,
        message_id=message_id,
    )

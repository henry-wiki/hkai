#!/usr/bin/env python3
"""
server_process.py — process 端點。

職責：
- scan()：POST /process/scan/（PHP 呼叫——依名稱清單列出本機程序；跨平台，PHP 內不再 exec／parse）
- kill()：POST /process/kill/（PHP 呼叫——終止本系統程序：pid ＋ 子程序樹，跨平台；落刀前寫殺戳日誌）

設計（效能關鍵）：
- 第一段只取 pid／name（快——Windows 約 0.08s／420 個程序）
- 第二段只對命中的程序補 ppid／cmdline（成本高；對全表補料在 Windows 可達 10s+）
"""

import json
import time
from pathlib import Path

from fastapi import APIRouter, Request

router = APIRouter()

# 預設映像檔名前綴（payload 未指定時用——四層程序共用同一顆 python）
DEFAULT_PROCESS_NAME_PREFIXES = ["python"]

# program 比對樣式（argv 級——" -m bridge.main"；不用裸 substring，避免誤中把字串寫在原始碼裡的程序）
PROGRAM_ARG_PREFIX = " -m "

# 血緣展開層數上限（worker → aider 為兩層；留裕度）
MAX_BLOOD_DEPTH = 5


def write_kill_journal(config: dict, knife_name: str, process_pid: int, image_name: str, source_text: str) -> None:
    """落刀前寫殺戳日誌（append 一行 JSON 到 process_status/kill_journal.jsonl——同 PHP 側同一份；失敗唔影響落刀）。"""
    # 判定：設定未帶 storage_dir → 放棄（日誌絕不影響主流程）
    try:
        storage_dir = config.get("storage_dir", "")

        if storage_dir == "":
            return

        folders = config.get("folders", {})
        journal_dir = Path(storage_dir) / folders.get("process_status", "process_status")
        journal_path = journal_dir / "kill_journal.jsonl"

        record = {
            "ts": time.strftime("%Y-%m-%d %H:%M:%S"),
            "knife": knife_name,
            "pid": int(process_pid or 0),
            "image": image_name,
            "source": source_text,
        }

        with open(journal_path, "a", encoding="utf-8", newline="\n") as journal_file:
            journal_file.write(json.dumps(record, ensure_ascii=False) + "\n")
    except Exception:  # noqa: BLE001
        pass  # 日誌寫入失敗絕不影響落刀


def build_process_row(process) -> dict:
    """組一列程序資料（逐項容錯——權限不足的程序該欄位留空）。"""
    info = process.info
    row = {
        "pid": int(info.get("pid") or 0),
        "name": str(info.get("name") or ""),
        "ppid": 0,
        "command_line": "",
        "matched_by": "",
    }

    # 判定：取不到 ppid（權限／已結束）→ 留 0
    try:
        row["ppid"] = int(process.ppid() or 0)
    except Exception:
        row["ppid"] = 0

    # 判定：取不到 cmdline（權限／已結束）→ 留空
    try:
        row["command_line"] = " ".join(process.cmdline())
    except Exception:
        row["command_line"] = ""

    return row


@router.post("/process/scan/")
def scan(request: Request, payload: dict) -> dict:
    """PHP 掃描本機程序（依名稱清單——先過濾、後補料）。"""
    started_time = time.time()

    # 判定：payload 未給名稱前綴（或給空陣列）→ 用預設清單
    process_name_prefixes = payload.get("process_name_prefixes")

    if not isinstance(process_name_prefixes, list) or len(process_name_prefixes) == 0:
        process_name_prefixes = DEFAULT_PROCESS_NAME_PREFIXES

    name_keys = [str(each_name).lower() for each_name in process_name_prefixes]

    # 判定：psutil 不可用 → 明確回錯誤（不比照舊路徑）
    try:
        import psutil
    except Exception as import_error:
        return {"ok": False, "error": "psutil unavailable: " + str(import_error)}

    matched_processes = []

    # 第一段：全表枚舉，只比對名稱（只取 pid／name）
    for process in psutil.process_iter(["pid", "name"]):
        process_name = str(process.info.get("name") or "").lower()

        # 判定：名稱不符任一前綴 → 跳過
        if not any(process_name.startswith(name_key) for name_key in name_keys):
            continue

        matched_processes.append(process)

    # payload program 清單（我們自己定的點分路徑——" -m <program>"；空＝不做第二關）
    program_names = payload.get("program_names")

    if not isinstance(program_names, list):
        program_names = []

    # 第二段：只對命中者補 ppid／cmdline
    processes = []

    for process in matched_processes:
        processes.append(build_process_row(process))

    # 判定：有給 program 清單 → 精篩＝根（cmdline 命中 " -m <program>"）＋ 血緣展開（子程序：worker／aider 由父程序 spawn，cmdline 無 -m）
    if len(program_names) > 0:
        root_rows = []

        for row in processes:
            is_program_root = any((PROGRAM_ARG_PREFIX + str(program_name)) in row["command_line"] for program_name in program_names)

            # 判定：cmdline 命中 program → 根（bridge／aider_daemon）
            if is_program_root:
                row["matched_by"] = "program"
                root_rows.append(row)

        kept_rows = list(root_rows)

        # 血緣展開：父程序在集合內 → 收為子程序（逐層；上限 MAX_BLOOD_DEPTH）
        for blood_depth in range(MAX_BLOOD_DEPTH):
            kept_pids = set(row["pid"] for row in kept_rows)
            added_rows = []

            for row in processes:
                # 判定：父程序在已收集合內、且自己未收 → 加入（child of）
                if row["ppid"] in kept_pids and row["pid"] not in kept_pids and row not in kept_rows:
                    row["matched_by"] = "lineage"
                    added_rows.append(row)

            # 判定：本層沒有新加入 → 血緣展開結束
            if len(added_rows) == 0:
                break

            kept_rows = kept_rows + added_rows

        processes = kept_rows

    processes.sort(key=lambda row: row["pid"])

    return {
        "ok": True,
        "processes": processes,
        "scan_seconds": round(time.time() - started_time, 3),
    }


@router.post("/process/kill/")
def kill(request: Request, payload: dict) -> dict:
    """PHP 終止本系統程序（pid ＋ 子程序樹——psutil 跨平台；白名單由 PHP 判定）。"""
    kill_pid = int(payload.get("pid") or 0)

    # 判定：pid 無效 → 拒絕
    if kill_pid < 1:
        return {"ok": False, "error": "invalid pid"}

    # 判定：psutil 不可用 → 拒絕
    try:
        import psutil
    except Exception as import_error:
        return {"ok": False, "error": "psutil unavailable: " + str(import_error)}

    # 判定：pid 不在本機（已被殺／不存在）→ 拒絕
    if not psutil.pid_exists(kill_pid):
        return {"ok": False, "error": "pid not found"}

    try:
        target_process = psutil.Process(kill_pid)
    except Exception as process_error:
        return {"ok": False, "error": str(process_error)}

    # 落刀前寫殺戳日誌（刀名／pid／映像名／來源——統一留痕；失敗唔影響落刀）
    child_pids_for_log = payload.get("child_pids") or []

    # 判定：child_pids 唔係陣列（非預期輸入）→ 當空
    if not isinstance(child_pids_for_log, list):
        child_pids_for_log = []

    # 判定：取唔到映像名（權限／已死）→ 留空
    try:
        target_image_name = target_process.name()
    except Exception:  # noqa: BLE001
        target_image_name = ""

    kill_source_text = str(payload.get("source", "")) + ";children=" + str(len(child_pids_for_log))
    write_kill_journal(request.app.state.config, "bridge:/process/kill/", kill_pid, target_image_name, kill_source_text)

    # 先殺子（child_pids 由 PHP 依台帳血緣排好——deepest-first），再殺父
    killed_children = []

    for child_pid in child_pids_for_log:
        try:
            child_pid = int(child_pid)
        except Exception:
            continue

        # 判定：子程序已不在／無效 → 跳過
        if child_pid < 1 or not psutil.pid_exists(child_pid):
            continue

        try:
            psutil.Process(child_pid).kill()
            killed_children.append(child_pid)
        except Exception:
            continue

    try:
        target_process.kill()
    except Exception as kill_error:
        return {"ok": False, "error": str(kill_error)}

    return {"ok": True, "pid": kill_pid, "children": killed_children}

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false, reportUnusedCallResult=false
#!/usr/bin/env python3
"""
job_store.py — 工作目錄讀寫（job.json＋每張圖一個狀態檔）。

契約：
    data/_tmp/image_extract/<message_id>/job.json          輸入＋pid＋時間
    data/_tmp/image_extract/<message_id>/<idx>.status.json 每張圖一個狀態（一個檔案一個真相）

狀態值：pending／running／done／failed／cancelled
整體狀態由各檔案派生（唔另寫 aggregate 檔）。

上游：main／extractor
下游：context（路徑）、json／pathlib（stdlib）
"""

import json
import time
from pathlib import Path
from typing import Any


VALID_FILE_STATES = ["pending", "running", "done", "failed", "cancelled"]


def read_job(job_dir: Path) -> dict[str, Any]:
    """讀 job.json；缺失／壞檔 → 空 dict（呼叫端自行判斷）。"""
    job_file = job_dir / "job.json"

    # 判定：檔案唔存在 → 空
    if job_file.is_file() is False:
        return {}

    try:
        return json.loads(job_file.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}


def write_job(job_dir: Path, job_data: dict[str, Any]) -> None:
    """寫 job.json（atomic——先寫 .tmp 再 replace）。"""
    job_dir.mkdir(parents=True, exist_ok=True)

    job_file = job_dir / "job.json"
    temp_file = job_dir / "job.json.tmp"
    temp_file.write_text(json.dumps(job_data, ensure_ascii=False, indent=2), encoding="utf-8")
    temp_file.replace(job_file)


def file_status_path(job_dir: Path, file_index: int) -> Path:
    """單張圖狀態檔路徑。"""
    return job_dir / (str(int(file_index)) + ".status.json")


def read_file_status(job_dir: Path, file_index: int) -> dict[str, Any]:
    """讀單張圖狀態；缺失／壞檔 → 空 dict。"""
    status_file = file_status_path(job_dir, file_index)

    # 判定：檔案唔存在 → 空
    if status_file.is_file() is False:
        return {}

    try:
        return json.loads(status_file.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}


def write_file_status(job_dir: Path, file_index: int, status_data: dict[str, Any]) -> None:
    """寫單張圖狀態（atomic）。"""
    job_dir.mkdir(parents=True, exist_ok=True)

    status_data["idx"] = int(file_index)
    status_data["updated_at"] = time.strftime("%Y-%m-%d %H:%M:%S")

    status_file = file_status_path(job_dir, file_index)
    temp_file = job_dir / (str(int(file_index)) + ".status.json.tmp")
    temp_file.write_text(json.dumps(status_data, ensure_ascii=False, indent=2), encoding="utf-8")
    temp_file.replace(status_file)


def list_file_statuses(job_dir: Path, job_data: dict[str, Any]) -> list[dict[str, Any]]:
    """按 job 內 files 次序讀全部狀態（缺檔＝pending 佔位）。"""
    status_list: list[dict[str, Any]] = []
    job_files = job_data.get("files", [])

    # 判定：job 冇 files → 空清單
    if isinstance(job_files, list) is False:
        return status_list

    for job_file_item in job_files:
        file_index = int(job_file_item.get("idx", 0))

        status_data = read_file_status(job_dir, file_index)

        # 判定：狀態檔未建立 → 當 pending
        if status_data == {}:
            status_data = {"idx": file_index, "state": "pending"}

        status_list.append(status_data)

    return status_list


def derive_overall_state(status_list: list[dict[str, Any]]) -> str:
    """由各檔案狀態派生整體狀態。"""
    # 判定：冇任何檔案 → pending
    if len(status_list) < 1:
        return "pending"

    state_list = [str(status_item.get("state", "pending")) for status_item in status_list]

    # 判定：有 cancelled → cancelled（最高優先）
    if "cancelled" in state_list:
        return "cancelled"

    # 判定：全部 done → done
    if all(state_item == "done" for state_item in state_list):
        return "done"

    # 判定：全部已完結（done／failed）且有 failed → done_with_errors
    is_all_finished = all(state_item in ("done", "failed") for state_item in state_list)
    if is_all_finished is True and "failed" in state_list:
        return "done_with_errors"

    # 判定：有 running → running
    if "running" in state_list:
        return "running"

    return "pending"

#!/usr/bin/env python3
"""
pidfile.py — 抽取進程 pid 檔＋終止（bridge stop 用）。

職責：
- write_pid／read_pid／remove_pid（data/_tmp/image_extract/<message_id>/extract.pid）
- terminate_tree()：連子進程一齊終止（terminate → 等 3 秒 → kill）

上游：main（run／stop）、bridge（經 CLI stop）
下游：psutil（第三方）、context（JOBS_DIR）
"""

from pathlib import Path

import psutil

# 終止寬限（秒——同其他套件一致）
KILL_WAIT_SEC = 3.0

PID_FILE_NAME = "extract.pid"


def pid_file_path(job_dir: Path) -> Path:
    """pid 檔路徑。"""
    return job_dir / PID_FILE_NAME


def write_pid(job_dir: Path, pid: int) -> None:
    """寫入本進程 pid。"""
    job_dir.mkdir(parents=True, exist_ok=True)
    _ = pid_file_path(job_dir).write_text(str(int(pid)), encoding="utf-8")


def read_pid(job_dir: Path) -> int:
    """讀 pid；缺失／壞檔 → 0。"""
    pid_file = pid_file_path(job_dir)

    # 判定：冇 pid 檔 → 0
    if pid_file.is_file() is False:
        return 0

    try:
        return int(pid_file.read_text(encoding="utf-8").strip())
    except (OSError, ValueError):
        return 0


def remove_pid(job_dir: Path) -> None:
    """刪 pid 檔（唔存在＝no-op）。"""
    pid_file = pid_file_path(job_dir)

    # 判定：檔案存在 → 刪
    if pid_file.is_file() is True:
        pid_file.unlink(missing_ok=True)


def terminate_tree(pid: int) -> bool:
    """連子進程終止；回 True＝有殺到，False＝本身已死。"""
    # 判定：pid 無效 → 唔做
    if pid <= 0:
        return False

    try:
        parent_process = psutil.Process(pid)
    except psutil.Error:
        return False

    process_list = [parent_process]
    try:
        process_list = process_list + parent_process.children(recursive=True)
    except psutil.Error:
        process_list = [parent_process]

    # 先 terminate 全部（由子到母）
    for process_item in reversed(process_list):
        try:
            process_item.terminate()
        except psutil.Error:
            continue

    _gone_list, alive_list = psutil.wait_procs(process_list, timeout=KILL_WAIT_SEC)  # pyright: ignore[reportUnknownMemberType]

    # 判定：仍有未死 → 強制 kill
    for alive_item in alive_list:
        try:
            alive_item.kill()
        except psutil.Error:
            continue

    return True

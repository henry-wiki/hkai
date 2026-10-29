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


def is_pid_running(pid: int) -> bool:
    """判定 pid 係唔係仍活著（psutil）。"""
    # 判定：pid 無效 → False
    if pid <= 0:
        return False

    try:
        return psutil.Process(pid).is_running()
    except psutil.Error:
        return False


def claim_pid(box_dir: Path, pid: int) -> bool:
    """原子認領（箱內 extract.pid）：冇人／舊主人已死＝成功；活主人＝False。"""
    box_dir.mkdir(parents=True, exist_ok=True)

    existing_pid = read_pid(box_dir)

    # 判定：已有活主人 → 認領失敗
    if existing_pid > 0 and is_pid_running(existing_pid) is True:
        return False

    write_pid(box_dir, pid)

    return True


def release_pid(box_dir: Path, pid: int) -> None:
    """交還認領（只刪自己嗰個）。"""
    # 判定：pid 檔係自己 → 刪
    if read_pid(box_dir) == int(pid):
        remove_pid(box_dir)


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

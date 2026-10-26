#!/usr/bin/env python3
"""
job_scanner.py — polling 工單表（記憶體；增量載入＋派工標記）

職責：
- load()                    掃 polling 目錄 → 只補缺失（已載入唔重讀）；按 daemon_engine 分組；建立時間升序
- get_pending_jobs(agent)  取本agent可派工作（未派＋非終態＋無活主人；建立時間 asc）
- update_job(job_key, ...)  更新工單欄位（派工／完成；唔重讀檔）

上游：daemon_manager._tick()（每拍 load）；job_scheduler.dispatch()（get_pending_jobs／update_job）
下游：message_store.get_msg_conf_files()（路徑／排序唯一來源）

工單表：{daemon_engine: {job_key: {"path", "chat_id", "message_id", "assigned"}}}
job_key＝msg_conf 路徑（唯一；唔使解析內容就拿到）。
"""

import json
from pathlib import Path
from typing import TypedDict, cast

from ..context import message_status, message_store
from ..runtime import process, sc_logger
from ..utils import io


# 工單條目契約（msg_conf 讀出——派工狀態另存 assigned）
class JobEntry(TypedDict):
    """工單表條目（每張 msg_conf 一條）。"""

    path: Path
    job_key: str
    daemon_engine: str
    chat_id: str
    message_id: str
    connection_timeout: int
    startup_timeout: int
    assigned: bool


# 工單表（agent → {job_key: job}；dict 保留插入次序＝掃描次序＝建立時間 asc）
_poll_jobs: dict[str, dict[str, JobEntry]] = {}


def load() -> None:
    """掃 polling 目錄 → 只補缺失工單（已存在唔重讀）；順便清走已消失嘅工單。"""
    scanned_paths = message_store().get_msg_conf_files()
    scanned_keys: set[str] = set()

    for msg_conf_path in scanned_paths:
        job_key = str(msg_conf_path)
        scanned_keys.add(job_key)

        # 判定：已載入（任何agent組）→ 唔重讀
        if _find_job(job_key) is not None:
            continue

        job = _read_job(msg_conf_path, job_key)

        # 判定：讀唔到／缺識別 → 跳過（下拍再試）
        if job is None:
            continue

        agent_jobs = _poll_jobs.setdefault(job["daemon_engine"], {})
        agent_jobs[job_key] = job

    _remove_missing_jobs(scanned_keys)


def get_pending_jobs(daemon_engine: str) -> list[JobEntry]:
    """取本agent全部未派工作（dict 插入序＝建立時間 asc；唔改狀態）。"""
    agent_jobs = _poll_jobs.get(daemon_engine, {})
    pending_jobs: list[JobEntry] = []

    for job in agent_jobs.values():
        # 判定：已派 → 跳過
        if job["assigned"] is True:
            continue

        # 判定：終態或已有活主人 → 唔可派
        if _is_job_ready(job) is False:
            continue

        pending_jobs.append(job)

    return pending_jobs


def _is_job_ready(job: JobEntry) -> bool:
    """即時狀態判斷：已終態／已有活主人 → False（狀態會變，唔可以喺 load 時快取）。"""
    current_status = message_store().get_msg_status(job["path"])

    # 判定：已終態（PHP 尚未歸檔）→ 不可派
    if message_status().is_terminal_status(current_status):
        return False

    # 判定：preparing（圖片抽取中）→ 未放行，不可派
    if current_status == "preparing":
        return False

    # 判定：該單已有活主人（讀該目錄 msg_claim.json）→ 不可派
    claim_path = Path(job["path"]).parent / "msg_claim.json"
    claim_data = io.read_json(claim_path)

    # 判定：無認領檔／壞檔 → 冇主人，可派
    if claim_data is None:
        return True

    owner_pid = int(str(claim_data.get("worker_pid", 0)))

    # 判定：主人 pid 無效 → 可派
    if owner_pid <= 0:
        return True

    return process.is_process_running(owner_pid) is False


def update_job(job_key: str, assigned: bool) -> None:
    """更新工單派工標記（只此一欄——唔重讀檔）。"""
    job = _find_job(job_key)

    # 判定：工單唔存在 → 唔動作
    if job is None:
        return

    job["assigned"] = assigned


def _find_job(job_key: str) -> JobEntry | None:
    """喺所有agent組搵工單（回 job；冇＝None）。"""
    for agent_jobs in _poll_jobs.values():
        # 判定：本組有 → 回傳
        if job_key in agent_jobs:
            return agent_jobs[job_key]

    return None


def _read_job(msg_conf_path: Path, job_key: str) -> JobEntry | None:
    """讀單一 msg_conf → 工單 dict（讀唔到／缺 chat 或 message → None）。"""
    try:
        conf_data = cast("object", json.loads(msg_conf_path.read_text(encoding="utf-8")))
    except Exception as exc:  # noqa: BLE001
        sc_logger.log("msg_conf read failed: " + str(msg_conf_path) + " | " + str(exc))
        return None

    # 判定：非 dict → 不可用
    if not isinstance(conf_data, dict):
        sc_logger.log("msg_conf not dict: " + str(msg_conf_path))
        return None

    conf_map = cast("dict[str, object]", conf_data)

    chat_id = str(conf_map.get("chat_id", ""))
    message_id = str(conf_map.get("message_id", ""))

    # 判定：缺 chat_id → 不可用
    if chat_id == "":
        sc_logger.log("msg_conf missing chat_id: " + str(msg_conf_path))
        return None

    # 判定：缺 message_id → 不可用
    if message_id == "":
        sc_logger.log("msg_conf missing message_id: " + str(msg_conf_path))
        return None

    return {
        "path"                  : msg_conf_path,
        "job_key"               : job_key,
        "daemon_engine"         : str(conf_map.get("daemon_engine", "")),
        "chat_id"               : chat_id,
        "message_id"            : message_id,
        "connection_timeout"    : int(str(conf_map.get("connection_timeout", 600))),
        "startup_timeout"       : int(str(conf_map.get("startup_timeout", 30))),
        "assigned"              : False,
    }


def _remove_missing_jobs(scanned_keys: set[str]) -> None:
    """清走已消失（PHP 已歸檔）嘅工單。"""
    for agent_jobs in _poll_jobs.values():
        for job_key in list(agent_jobs.keys()):
            # 判定：掃描清單已冇 → 移除
            if job_key not in scanned_keys:
                del agent_jobs[job_key]

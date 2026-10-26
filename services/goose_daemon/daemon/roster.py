#!/usr/bin/env python3
"""
roster.py — 工人名冊（邊個工人存在＋基本資料；零決策）

職責：
- add_worker_entry()     登記一條工人（pid → entry）
- remove_worker_entry()  登出工人（唔存在＝唔動作）
- get_worker_entries()   全部工人名冊（pid → entry）
- has_worker()           該對話是否已有工人（同對話一次一條）
- find_idle_worker()     第一條空閒工人（程序活住＋狀態 idle）

上游：job_scheduler（spawn 即登記／回收即登出）
下游：無（純資料；唔建立、唔殺、唔判斷）
範圍：daemon 程序記憶體——每個 daemon 自己一份名冊
"""

from multiprocessing import Process
from multiprocessing.queues import Queue
from typing import TypedDict


# 工人名冊條目——建立即有（身份／管道／狀態）
class WorkerEntryRequired(TypedDict):
    """spawn 時一定有嘅欄位。"""

    proc: Process
    job_queue: "Queue[str | None]"
    status_queue: "Queue[dict[str, object]]"
    started_at: int
    worker_status: str
    status_updated_at: int
    last_signal_at: int


# 工人名冊條目——派工後補上（total=False）
class WorkerEntry(WorkerEntryRequired, total=False):
    """名冊條目契約（工單欄位派工後才出現）。"""

    chat_id: str
    message_id: str
    msg_conf_path: str
    dispatched_at: int
    job_connection_timeout: int
    job_startup_timeout: int


# 工人名冊（pid → worker_entry；daemon 全程序共用同一個 dict）
_worker_entries: dict[int, WorkerEntry] = {}


def add_worker_entry(worker_pid: int, worker_entry: WorkerEntry) -> None:
    """登記一條工人（建立即登記；pid → entry）。"""
    _worker_entries[worker_pid] = worker_entry


def remove_worker_entry(worker_pid: int) -> None:
    """登出工人（死亡／終止後；唔存在＝唔動作）。"""
    _ = _worker_entries.pop(worker_pid, None)


def get_worker_entries() -> dict[int, WorkerEntry]:
    """全部工人名冊（pid → entry；回同一個 dict——迭代用）。"""
    return _worker_entries


def has_worker(chat_id: str) -> bool:
    """該對話是否已有工人（查名冊——零決策）。"""
    for worker_entry in _worker_entries.values():
        # 判定：同 chat_id → 已有工人喺手
        if worker_entry.get("chat_id") == chat_id:
            return True
    return False


def find_idle_worker() -> WorkerEntry | None:
    """回傳第一條空閒工人（程序活住＋狀態 idle）；冇＝None。"""
    for worker_entry in _worker_entries.values():
        # 判定：程序已死 → 非空閒（等回收）
        if worker_entry["proc"].is_alive() is False:
            continue

        # 判定：狀態唔係 idle → 非空閒
        if worker_entry.get("worker_status") != "idle":
            continue

        return worker_entry

    return None

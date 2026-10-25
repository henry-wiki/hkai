#!/usr/bin/env python3
"""
roster.py — 工人名冊（邊個工人存在＋基本資料；零決策）

職責：
- add_worker_entry()     登記一條工人（pid → entry）
- remove_worker_entry()  登出工人（唔存在＝唔動作）
- get_worker_entries()   全部工人名冊（pid → entry）
- has_worker()           該對話是否已有工人（同對話一次一條）
- find_idle_worker()      第一條空閒工人（程序活住＋狀態 idle）

上游：job_scheduler（spawn 即登記／回收即登出）
下游：無（純資料；唔建立、唔殺、唔判斷）
範圍：daemon 程序記憶體——每個 daemon 自己一份名冊
"""

# 工人名冊（pid → worker_entry；daemon 全程序共用同一個 dict）
_worker_entries: dict[int, dict] = {}


def add_worker_entry(worker_pid: int, worker_entry: dict) -> None:
    """登記一條工人（建立即登記；pid → entry）。"""
    _worker_entries[worker_pid] = worker_entry


def remove_worker_entry(worker_pid: int) -> None:
    """登出工人（死亡／終止後；唔存在＝唔動作）。"""
    _worker_entries.pop(worker_pid, None)


def get_worker_entries() -> dict:
    """全部工人名冊（pid → entry；回同一個 dict——迭代用）。"""
    return _worker_entries


def has_worker(chat_id: str) -> bool:
    """該對話是否已有工人（查名冊——零決策）。"""
    for worker_entry in _worker_entries.values():
        # 判定：同 chat_id → 已有工人喺手
        if worker_entry["chat_id"] == chat_id:
            return True
    return False


def find_idle_worker():
    """回傳第一條空閒工人（程序活住＋狀態 idle）；冇＝None。"""
    for worker_entry in _worker_entries.values():
        # 判定：程序已死 → 非空閒（等回收）
        if not worker_entry["proc"].is_alive():
            continue

        # 判定：狀態唔係 idle → 非空閒
        if worker_entry["worker_status"] != "idle":
            continue

        return worker_entry

    return None

#!/usr/bin/env python3
"""
job_signal.py — 工單完成訊號（worker → daemon 回報）

職責：
- collect_worker_signals()  行勻名冊，逐條工人收訊號（睇一次，唔等）
- handle_worker_signal()    處理單一訊號（job_done＝工人回空閒）

上游：daemon_manager._tick()（每拍第四步）
下游：runner.run（做完一張單 put job_done 入 status_queue）
狀態：工人名冊由 roster 封裝（get_worker_entries）
方向：worker → daemon（同 job_queue 嘅 daemon → worker 相反）
"""

import queue
import time

from ..runtime import sc_logger
from . import roster


def collect_worker_signals() -> None:
    """行勻每條工人，收盡佢嘅訊號（睇一次即走，唔會停低等）。"""
    # copy 一份名冊再行——收訊號期間名冊可能因回收而改動
    for worker_entry in list(roster.get_worker_entries().values()):
        _drain_worker_signals(worker_entry)


def _drain_worker_signals(worker_entry: dict) -> None:
    """掏乾一條工人嘅訊號信箱（status_queue）；信箱空＝即刻走。"""
    # 每條工人一條 status_queue（spawn_worker() 開工人時建立）
    status_queue = worker_entry.get("status_queue")

    # 判定：冇訊號通道 → 跳過
    if status_queue is None:
        return

    while True:
        try:
            # get_nowait：唔等——有就即刻拎，冇就 raise queue.Empty
            worker_signal = status_queue.get_nowait()
        # 信箱已空 → 收工（正常結束，唔係錯誤）
        except queue.Empty:
            return

        handle_worker_signal(worker_entry, worker_signal)


def handle_worker_signal(worker_entry: dict, worker_signal: dict) -> None:
    """處理單一訊號：job_done → 清空該工人手上嘅工單（回空閒，等下張）。"""
    signal_event = str(worker_signal.get("event", ""))

    # 任何訊號都記錄「最後有反應時間」（唔理種類——用嚟判工人仲有冇反應）
    worker_entry["last_signal_at"] = int(time.time())

    # 判定：唔係完成訊號 → 略過（現時只有 job_done 一種）
    if signal_event != "job_done":
        return

    # 判定：工單失敗收工 → 唔放回空閒（工人即將退出；交 reap_workers 收屍）
    if str(worker_signal.get("outcome", "")) == "failed":
        sc_logger.log("worker job failed, pending reap pid=" + str(worker_entry["proc"].pid))
        return

    # 狀態轉 idle（名冊唯一判斷依據）＋記錄轉態時間
    worker_entry["worker_status"] = "idle"
    worker_entry["status_updated_at"] = int(time.time())

    # 清空工單資料 → 下一拍 find_idle_worker() 就可以再派單畀佢
    worker_entry["chat_id"] = ""
    worker_entry["message_id"] = ""
    worker_entry["msg_conf_path"] = ""
    worker_entry["dispatched_at"] = 0

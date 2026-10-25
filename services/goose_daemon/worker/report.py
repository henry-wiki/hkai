#!/usr/bin/env python3
"""
report.py

worker → daemon 生命週期上報（唯一出口——只報「daemon 需要行動」嘅事件）。

職責：組事件 dict → status_queue.put()（唔碰 msg_status；狀態由 message_status 記錄）。
事件：job_done（outcome＝success／failed／skipped——daemon 據此放工；failed 工人隨後自殺）。

上游：runner（做完一張單之後）
下游：status_queue（multiprocessing.Queue——daemon 側 job_signal 讀）
"""

import multiprocessing


def job_done(status_queue: "multiprocessing.Queue[dict[str, object]]", worker_pid: int, outcome: str) -> None:
    """上報一張工單結束（outcome＝success／failed／skipped）。"""
    event: dict[str, object] = {
        "event": "job_done",
        "pid": worker_pid,
        "outcome": outcome,
    }

    status_queue.put(event)

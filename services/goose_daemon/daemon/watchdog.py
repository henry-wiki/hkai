#!/usr/bin/env python3
"""
watchdog.py — 工人看門狗（巡邏：取消／逾時 → 殺；死亡收尾）

職責：
- supervise_workers()   逐忙碌 worker 偵測異常 → 停
- detect_stall()        依訊息現狀＋經過秒數判定異常原因
- stop_worker()         安全終止＋收尾＋登出
- cleanup_dead_worker() 工單在身死亡 → 幫該訊息收終態

上游：daemon_manager._tick()；job_scheduler.reap_workers()
下游：message_store（讀狀態）／job_executor（收終態）／lifecycle.exit_child（終止）
狀態：工人名冊由 roster 封裝（get_worker_entries／remove_worker_entry）
"""

import time
from pathlib import Path
from typing import cast

from ..context import job_executor, message_store
from ..runtime import lifecycle, sc_logger
from . import roster


def supervise_workers() -> None:
    """逐忙碌 worker 偵測異常——異常即停（worker 阻塞中無法自理）。"""
    for worker_pid, worker_entry in list(roster.get_worker_entries().items()):

        # 判定：唔係 working → 略過（狀態由 worker_status 講）
        if worker_entry["worker_status"] != "working":
            continue

        # 判定：程序已死 → 交回收處理
        if not worker_entry["proc"].is_alive():
            continue

        kill_reason = detect_stall(worker_entry)

        # 判定：正常 → 繼續工作
        if kill_reason == "":
            continue

        sc_logger.log("warm worker killed pid=" + str(worker_pid) + " reason=" + kill_reason)
        stop_worker(worker_entry, kill_reason)


def detect_stall(worker_entry: roster.WorkerEntry) -> str:
    """依訊息現狀＋經過秒數判定是否異常（回原因；正常＝空字串）。"""
    msg_conf_path = Path(worker_entry.get("msg_conf_path", ""))
    current_status = message_store().get_msg_status(msg_conf_path)
    elapsed_sec = int(time.time()) - int(worker_entry.get("dispatched_at", 0))

    # 判定：使用者已取消 → 立即終止
    if current_status == "cancelled":
        return "cancelled"

    # 判定：未取單（queued）→ 寬限＝啟動＋連線逾時
    if current_status == "queued":
        queued_deadline = int(worker_entry.get("job_startup_timeout", 0)) + int(worker_entry.get("job_connection_timeout", 0))

        # 判定：超出寬限 → 逾時
        if elapsed_sec > queued_deadline:
            return "queued timeout"

        return ""

    # 判定：已開跑未見首個回應（connecting）→ 寬限＝連線逾時
    if current_status == "connecting":
        connection_deadline = int(worker_entry.get("job_connection_timeout", 0))

        # 判定：超出寬限 → 逾時
        if elapsed_sec > connection_deadline:
            return "connect timeout"

        return ""

    return ""


def stop_worker(worker_entry: roster.WorkerEntry, reason: str) -> None:
    """安全終止 worker＋收尾＋登出。"""
    worker_proc = worker_entry["proc"]

    # 存活程序必有 pid（supervise 已判 is_alive）——型別收窄
    worker_pid = cast("int", worker_proc.pid)

    lifecycle.exit_child(worker_pid, reason)
    worker_proc.join()

    cleanup_dead_worker(worker_entry)
    roster.remove_worker_entry(worker_pid)

    sc_logger.log("warm worker stopped pid=" + str(worker_pid) + " reason=" + reason)


def cleanup_dead_worker(worker_entry: roster.WorkerEntry) -> None:
    """工單在身死亡 → 幫該訊息收終態（防卡 connecting）。"""
    # 判定：死時無工單 → 唔使收尾
    if worker_entry.get("chat_id", "") == "":
        return

    job_executor().finalize_abandoned_message(Path(worker_entry.get("msg_conf_path", "")))

#!/usr/bin/env python3
"""
worker.py

goose_daemon worker 子程序入口（由 daemon 調度器 spawn；承載 msg_conf 的處理語境）。

兩種入口：
    run_worker       冷路徑——一個 worker 跑一條（daemon 每訊息 spawn；內含 goose 子程序）
    run_warm_worker  暖路徑——常駐（載入引擎一次；迴圈收工單；同程序執行、無子程序）

職責：在獨立程序內重新載入 daemon 設定 → 交給 msg_processor 完成查詢（阻塞的是 worker，不是 daemon）。

生命週期：由 daemon 主程序統一管理（registry 記錄／回收／淘汰）——worker 不自殺、不寫 registry。

程序狀態：worker 自己統一由本程序的心跳執行緒每秒寫一次（process_status）。
另：心跳執行緒同時兼任子程序記憶體守衛（超限中止——child_memory_guard.py）。
"""

import os
import psutil
import queue
import threading
import time
from pathlib import Path

# 心跳間隔（秒——與 daemon／bridge 同節奏）
HEARTBEAT_INTERVAL_SEC = 1.0


def run_worker(daemon_conf_path: str, msg_conf_path: str) -> None:
    """worker 入口（multiprocessing target——module 級函數，Windows spawn 可 pickle）。"""
    from ..context import daemon, msg_processor
    from ..runtime import process_status

    # 子程序重新載入 daemon 設定（spawn 後為新程序——同 goose_entry.run_coder 模式）
    daemon().load_daemon_conf_data(Path(daemon_conf_path), is_reload=True)

    # 程序狀態（自我心跳）：登記自己 ＋ 開心跳執行緒
    # （worker 全期多在等子程序，不靠主流程打點——由執行緒每秒寫檔）
    process_status.initialize(daemon().get_daemon_conf_data()["process_status_dir"])
    process_status.register("worker", os.getpid(), "goose_daemon.worker")
    start_heartbeat()

    msg_processor().process(Path(msg_conf_path))


def run_warm_worker(daemon_conf_path: str, job_queue, status_queue) -> None:
    """暖 worker 入口（常駐：預載引擎一次 → 迴圈收工單；生死全由 daemon 裁決）。"""
    from ..context import daemon, goose_entry, msg_processor
    from ..runtime import process_status

    # 子程序重新載入 daemon 設定（spawn 後為新程序——同 run_worker 模式）
    daemon().load_daemon_conf_data(Path(daemon_conf_path), is_reload=True)

    # 程序狀態（自我心跳）：登記自己 ＋ 開心跳執行緒（全期覆寫——長駐）
    process_status.initialize(daemon().get_daemon_conf_data()["process_status_dir"])
    process_status.register("worker", os.getpid(), "goose_daemon.warm_worker")
    start_heartbeat()

    # 預載檢查（goose 執行檔——輕量；整條 worker 生命只做一次）
    goose_entry().preload()

    # 就緒回報（daemon 只派工單畀已就緒的暖 worker）
    status_queue.put({"event": "ready", "pid": os.getpid()})

    # daemon pid 記錄（換代偵測——父程序消失即退出，防孤兒常駐）
    daemon_pid = os.getppid()

    # 工單迴圈：收一張跑一張（同一時間一條——同對話序列化由 daemon 派工保證）
    while True:
        try:
            msg_conf_path = job_queue.get(timeout=30)
        # 逾時＝暫時無工單——順便檢查 daemon 是否仍在
        except queue.Empty:
            # 判定：daemon 已消失（換代／被殺）→ 自行退出
            if not _is_daemon_alive(daemon_pid):
                return
            continue

        # 判定：哨兵（None）→ 收工結束
        if msg_conf_path is None:
            return

        msg_processor().process(Path(msg_conf_path))

        # 完成回報（daemon 據此清空「當前對話」→ 可再派下一張）
        status_queue.put({"event": "job_done", "pid": os.getpid()})


def _is_daemon_alive(daemon_pid: int) -> bool:
    """父 daemon 是否仍在（防孤兒——Windows 舊 ppid 沿用，須實查程序）。"""
    return psutil.pid_exists(int(daemon_pid))


def start_heartbeat() -> None:
    """背景心跳執行緒（每秒 beat_all——覆寫本程序登記項；daemon thread 隨程序結束消失）。"""
    from ..context import child_memory_guard
    from ..runtime import process_status

    def _beat_loop() -> None:
        while True:
            process_status.beat_all()
            # 監護人職責：每拍檢查子樹記憶體（超限即中止）
            child_memory_guard().check()
            time.sleep(HEARTBEAT_INTERVAL_SEC)

    heartbeat_thread = threading.Thread(target=_beat_loop, daemon=True, name="process_status_heartbeat")
    heartbeat_thread.start()

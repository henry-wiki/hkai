#!/usr/bin/env python3
"""
job_scheduler.py — 工單排程（分派／建立／查詢／回收；goose_daemon 專用）

職責：
- dispatch()               取未派工單 → 逐張交派（同對話一次一條；冇閒置即開新）
- spawn_worker()           開一條 worker 並即刻交派工單（建立即有單）
- dispatch_job()           工單入隊＋登記 worker 忙碌
- reap_workers()           回收已結束 worker

上游：daemon_manager._tick()（派工第三步）／reap（回收）
下游：runner.run／job_scanner.get_pending_jobs／sc_logger
狀態：工人名冊由 roster 管（add／remove／get_worker_entries）
"""

import contextlib
import multiprocessing
import time
from multiprocessing.queues import Queue
from pathlib import Path

from .. import context
from ..runtime import identity, sc_logger
from ..worker import runner
from ..worker.agent import paths
from . import job_scanner, roster, watchdog


def dispatch() -> None:
    """取未派工單逐張派工（非阻塞；同對話一次一條；冇閒置即開新 worker）。"""
    pending_jobs = job_scanner.get_pending_jobs(context.PROGRAM_NAME)

    for pending_job in pending_jobs:
        # 判定：該對話已有 worker 在處理 → 跳過
        # （同一對話同時只准一條 worker——goose session 每 chat 一條，並行會撞 session／history）
        if roster.has_worker(pending_job["chat_id"]):
            continue

        worker_entry: roster.WorkerEntry | None = roster.find_idle_worker()

        # 判定：揀到嘅工人心跳已停（閒置自殺進行中——程序仍在但唔會再收工單）→ 當冇閒置
        if worker_entry is not None and _is_worker_heartbeat_fresh(worker_entry) is False:
            worker_entry = None

        # 判定：冇閒置 worker → 即場開一條（建立即有單；queue 會緩衝）
        if worker_entry is None:
            worker_entry = spawn_worker(pending_job)
        else:
            dispatch_job(worker_entry, pending_job)

        # 判定：開 worker 失敗 → 停本輪（下輪再試）
        if worker_entry is None:
            break

        # 標記已派（同一件唔會再派）
        job_scanner.update_job(pending_job["job_key"], assigned=True)


# 工人心跳最多容許幾秒未更新（心跳每秒寫一次；超過＝工人正在退出）
WORKER_HEARTBEAT_STALE_SEC = 3


def _is_worker_heartbeat_fresh(worker_entry: roster.WorkerEntry) -> bool:
    """工人心跳仍新鮮？（閒置自殺中嘅工人程序未死但已停心跳——派單會石沉大海）"""
    worker_pid = worker_entry["proc"].pid
    status_file = Path(context.PROCESS_DIR) / (identity.WORKER_PROGRAM_NAME + "." + str(worker_pid) + ".json")

    # 判定：狀態檔唔存在 → 當唔新鮮
    if status_file.is_file() is False:
        return False

    age_sec = time.time() - status_file.stat().st_mtime

    return age_sec <= WORKER_HEARTBEAT_STALE_SEC


def spawn_worker(pending_job: job_scanner.JobEntry) -> roster.WorkerEntry | None:
    """開一條 worker 並即刻交派工單（建立即有單——工人只因工單而存在）。"""
    # 前置檢查：goose 執行檔唔可用 → 唔開工人（工單留 pending，下拍再試）
    if paths.resolve_executable() == "":
        sc_logger.log("spawn skipped: goose executable not found (config/PATH)")
        return None

    # 派單管道（daemon → worker）：put 工單路徑，worker get 到即執行
    job_queue: "Queue[str | None]" = multiprocessing.Queue()

    # 回報管道（worker → daemon）：worker 做完 put job_done，daemon 清空該工人當前對話
    status_queue: "Queue[dict[str, object]]" = multiprocessing.Queue()

    # 注意：worker 不可設 daemon=True——worker 內 goose 仍需建立子程序
    worker_proc = multiprocessing.Process(
        target=runner.run,
        args=(job_queue, status_queue, pending_job["chat_id"], pending_job["message_id"]),
        daemon=False,
    )
    worker_proc.start()

    # 判定：spawn 失敗（無 pid）→ 記 log 返回
    if worker_proc.pid is None:
        sc_logger.log("warm worker spawn failed")
        return None

    now_sec = int(time.time())

    worker_entry: roster.WorkerEntry = {
        # 身份／管道
        "proc"                 : worker_proc,
        "job_queue"            : job_queue,
        "status_queue"         : status_queue,
        "started_at"           : now_sec,

        # 狀態（名冊唯一判斷依據）
        "worker_status"        : "idle",
        "status_updated_at"    : now_sec,
        "last_signal_at"       : now_sec,

    }
    roster.add_worker_entry(worker_proc.pid, worker_entry)

    dispatch_job(worker_entry, pending_job)

    sc_logger.log("worker spawned pid=" + str(worker_proc.pid))

    return worker_entry


def dispatch_job(worker_entry: roster.WorkerEntry, pending_job: job_scanner.JobEntry) -> None:
    """工單入隊＋登記 worker 忙碌（同對話一次一條據此判定）。"""

    # 同一刻只計一次時間（三個時間欄位共用）
    now_sec = int(time.time())

    worker_entry["worker_status"]           = "working"
    worker_entry["status_updated_at"]       = now_sec
    worker_entry["last_signal_at"]          = now_sec

    worker_entry["chat_id"]                 = pending_job["chat_id"]
    worker_entry["message_id"]              = pending_job["message_id"]
    worker_entry["msg_conf_path"]           = str(pending_job["path"])
    worker_entry["dispatched_at"]           = now_sec
    worker_entry["job_connection_timeout"]  = pending_job["connection_timeout"]
    worker_entry["job_startup_timeout"]     = pending_job["startup_timeout"]

    # 注意：queue 只可以 .put() 送入管道；用 = 會覆寫 entry 指向嗰條 queue（管道即刻失效）
    worker_entry["job_queue"].put(str(pending_job["path"]))


    sc_logger.log(
        "job dispatched pid=" + str(worker_entry["proc"].pid)
        + " chat=" + pending_job["chat_id"]
        + " message=" + pending_job["message_id"])

def _remove_worker_status_files(worker_pid: int) -> None:
    """刪死者狀態檔（worker.<pid>.json／.json.tmp／.exit——檔案唔存在＝正常）。"""
    status_file_names = [
        identity.WORKER_PROGRAM_NAME + "." + str(worker_pid) + ".json",
        identity.WORKER_PROGRAM_NAME + "." + str(worker_pid) + ".json.tmp",
        identity.WORKER_PROGRAM_NAME + "." + str(worker_pid) + ".exit",
    ]

    # 逐個狀態檔刪（唔存在／被鎖 → 略過）
    for status_file_name in status_file_names:
        status_file = context.PROCESS_DIR / status_file_name
        with contextlib.suppress(OSError):
            status_file.unlink()


def reap_workers() -> None:
    """
    回收已結束 worker：join → 記 log（含運行秒數、exitcode）。

    存活 worker → 繼續（運行秒數即時計——唔使更新任何檔）。
    """
    # 逐 worker 檢查（copy keys——回收時會變更 dict）
    for worker_pid, worker_entry in list(roster.get_worker_entries().items()):
        worker_proc  = worker_entry["proc"]

        # 判定：仍在跑 → 本拍唔回收
        if worker_proc.is_alive():
            continue
        # 已死 → 以下四步收尾
        else:
            # 第一步：清走 OS zombie＋收 exit code（唔係等程序——佢已死，即時返回）
            worker_proc.join()

            # 第二步＋第三步：先幫死者收終態；無論成敗，第三步一定除名
            # （否則死者永遠佔住 chat_id 名額——同對話新工單永遠派唔出）
            try:
                watchdog.cleanup_dead_worker(worker_entry)
            finally:
                roster.remove_worker_entry(worker_pid)

            # 第三步之二：清死者狀態檔（心跳／tmp／撤退旗標——子程序唔跑 atexit，唔可以靠佢自己清）
            _remove_worker_status_files(worker_pid)

            # 第四步：記 log（pid／chat／message／運行秒數／exit code）
            elapsed_sec = int(time.time() - worker_entry["started_at"])

            sc_logger.log(
                "worker exited pid=" + str(worker_pid)
                + " chat=" + worker_entry.get("chat_id", "")
                + " message=" + worker_entry.get("message_id", "")
                + " elapsed=" + str(elapsed_sec) + "s"
                + " exitcode=" + str(worker_proc.exitcode))

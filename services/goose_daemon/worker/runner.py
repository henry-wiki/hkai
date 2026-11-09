#!/usr/bin/env python3
"""
runner.py

goose_daemon worker 子程序入口（由 daemon 調度器 spawn；承載 msg_conf 的處理語境）。

入口：
    run  常駐工人——載入agent一次；迴圈收工單；同程序執行、無子程序

職責：在獨立程序內重新載入 daemon 設定 → 交給 job_executor 完成查詢（阻塞的是 worker，不是 daemon）。

生命週期：由 daemon 主程序統一管理（roster 名冊記錄／job_scheduler 回收／watchdog 淘汰）；工人閒置超時自己退出（WARM_WORKER_IDLE_EXIT_SEC）。

程序狀態：worker 自己統一由本程序的心跳執行緒每秒寫一次（heartbeat）。
另：背景記憶體守衛交 memory_guard.start()（超限中止）。
"""

import queue
import time
from multiprocessing.queues import Queue
from pathlib import Path

from .. import context
from ..context import daemon, entry, job_executor, message_log, message_status
from bootstrap import sc_logger
from ..runtime import heartbeat_agent, heartbeat_worker, lifecycle, process
from . import memory_guard, report
from .agent import chat_mode


def _abort_job_on_mother_gone() -> None:
    """母親死收尾：標記取消（agent按正常流程停）→ 等agent退 → 自殺。

    上游：heartbeat_worker 心跳執行緒（母親唔在）
    下游：message_status／message_log（寫狀態）→ heartbeat_agent（讀agent pid）→ lifecycle.exit_self
    """
    agent_pid = heartbeat_agent.get_agent_pid()
    agent_create_time = heartbeat_agent.get_agent_create_time()

    # 判定：冇agent（工人閒置）→ 直接自殺
    if agent_pid == 0:
        lifecycle.exit_self("mother gone (idle)")

    # 1) 標記取消（watchdog 見到即送 session/cancel——agent按正常流程停）
    _ = message_status().apply("FAILURE_RECORDED")
    _ = message_log().append_error_log("MOTHER_GONE_ABORT", "mother gone — job aborted")

    # 2) 等agent退（最多 6 秒——唔強殺）
    _wait_agent_exit(agent_pid, agent_create_time, 6)

    # 3) 自殺（狀態已寫）
    lifecycle.exit_self("mother gone (job aborted)")


def _wait_agent_exit(agent_pid: int, agent_create_time: int, wait_seconds: int) -> None:
    """逐秒等agent消失（最多 wait_seconds 秒；核 create_time 防 pid 重用）。"""
    elapsed_seconds = 0

    # 逐秒檢查agent仍在否
    while elapsed_seconds < wait_seconds:
        # 判定：agent已消失（或被重用）→ 收工
        if _agent_is_alive(agent_pid, agent_create_time) is False:
            return

        time.sleep(1.0)
        elapsed_seconds = elapsed_seconds + 1


def _agent_is_alive(agent_pid: int, agent_create_time: int) -> bool:
    """agent仍在跑？（整數毫秒 create_time 精確對比——唔同＝pid 已被重用）。"""
    current_create_time = process.get_process_create_time(agent_pid)

    # 判定：讀唔到（已死）→ 唔在
    if current_create_time is None:
        return False

    # 判定：create_time 相同＝仍係同一程序（唔同＝pid 已被重用）
    return current_create_time == agent_create_time


def run(job_queue: "Queue[str | None]", status_queue: "Queue[dict[str, object]]", chat_id: str, message_id: str) -> None:
    """工人入口（常駐：預載agent一次 → 迴圈收工單；生死全由 daemon 裁決）。"""
    # 啟動即記錄自己 pid ＋母親 pid＋建立時間（生命週期判斷用）
    process.init()

    # 清潔工＝worker 自己嘅心跳檔（入口指名——避免模組 import 次序覆蓋）
    lifecycle.set_heartbeat_cleaner(heartbeat_worker.remove_heartbeat_file)

    # 子程序重新載入 daemon 設定（spawn 後為新程序——重新讀 runtime.json）
    _ = daemon().load_config()

    # chat_mode 對映（UI 模 → goose 原生模式；依本agent組重建）
    _ = chat_mode.init()

    # 母親死收尾次序（收工 → 寫狀態 → 自殺）——心跳執行緒呼叫
    heartbeat_worker.set_abort_handler(_abort_job_on_mother_gone)

    # 心跳（台帳一行 ＋ 狀態檔 ＋ 背景執行緒——單一入口）
    heartbeat_worker.init(chat_id, message_id)

    memory_guard.start()

    # 預載檢查（goose 執行檔——輕量；整條 worker 生命只做一次）
    entry().preload()

    # ── 工人主命脈：等單 → 做單 → 回報（循環到閒置超時或母親叫停為止）──
    # 收尾交 finally：multiprocessing 子程序正常 return 唔會跑 atexit（實測）——
    # 唔喺呢度收，心跳檔會留孤兒、台帳自己嗰行冇人清。
    # 等待方式：阻塞（blocking）——get() 停住等，唔輪詢、唔燒 CPU；
    #           管道一有單即由 OS 喚醒，冇單就等夠 30 秒（閒置自殺）。
    # 管道來源：job_queue 由 daemon 喺 spawn_worker() 建立，經 Process(args=...) 傳入；
    #           daemon 每次 put 一張工單嘅 meta.json 路徑（字串），工人喺下面 get 出嚟。
    try:
        while True:
            try:
                # 等工單：最多等 30 秒；收到＝一張工單嘅 meta.json 路徑字串
                # 注意：get() 係一次性取走（取後管道即空、唔會重播）；
                #       worker 取走後即刻死 → 管道冇副本，靠 daemon 側 worker_entry["msg_conf_path"] 收終態
                msg_conf_path = job_queue.get(timeout=context.WARM_WORKER_IDLE_EXIT_SEC)
            # 等足 30 秒都冇單＝閒置超時 → 工人自己收工（唔留長駐空殼浪費記憶體）
            except queue.Empty:
                sc_logger.log("worker idle timeout, exit pid=" + str(process.self_pid()))

                # 收工前先落標記：daemon 派工前見到即當本工人已不可派（同 daemon 層同一做法）
                heartbeat_worker.write_closing_marker()

                return

            # 哨兵（None）＝daemon 主動叫呢條工人收工（現時冇地方 put None——保留做安全出口）
            if msg_conf_path is None:
                heartbeat_worker.write_closing_marker()

                return

            # 載入本張工單語境（msg_conf → message_store 狀態；已終態＝False）
            job_ready = job_executor().initialize(Path(msg_conf_path))

            # 判定：已終態 → 唔使做單（照樣上報，等 daemon 放工）
            if job_ready is False:
                report.job_done(status_queue, process.self_pid(), "skipped")
                continue

            # 做單：阻塞直到整張工單完成（連線／回覆／收尾全部喺 job_executor 內）
            job_ok = job_executor().process()

            # 判定：中途出錯 → 上報 failed 後即刻收工（daemon 收屍；唔接下一張）
            if job_ok is False:
                report.job_done(status_queue, process.self_pid(), "failed")
                sc_logger.log("worker exits after job failure, pid=" + str(process.self_pid()))

                # 收工前先落標記（同 idle 超時同一做法）
                heartbeat_worker.write_closing_marker()

                return

            # 正常完成 → 上報 success，返去等下一張
            report.job_done(status_queue, process.self_pid(), "success")

    finally:
        # 收工（清心跳 → 清台帳自己嗰行 → 死亡出口）
        heartbeat_worker.stop("runner finished")

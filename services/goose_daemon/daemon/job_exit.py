#!/usr/bin/env python3
"""
job_exit.py — 經理收場（daemon 自殺協議：先禮後兵）

職責（次序固定）：
1. 掃描工人——冇工人 → 直接自殺
2. 先禮：逐個工人 job_queue 放哨兵 None（＝請你自殺）
3. 等 WORKER_EXIT_WAIT_SEC 秒（逐秒重掃；全走＝清場）
4. 後兵：9 秒仍在嘅工人 → lifecycle.exit_child(pid, reason)
5. 再掃一次——清場後經理自殺（lifecycle.exit_self）

上游：runtime/lifecycle.exit()（daemon 內任何自殺路徑——經 set_shutdown_handler 注入）
下游：roster（工人名冊）／job_scheduler.reap_workers（收屍）／lifecycle（殺／自殺）
"""

import time

from pathlib import Path

from .. import context
from ..runtime import lifecycle, sc_logger
from ..worker import identity
from . import job_scheduler, roster

# 先禮等待預設（秒——runtime.json `manager_exit_wait_sec` 可覆寫）
WORKER_EXIT_WAIT_SEC = 9.0

# 後兵之後再等（秒——被 terminate 嘅工人消失時間）
WORKER_KILL_WAIT_SEC = 2.0


def exit_manager(reason: str = "", exit_code: int = 0) -> None:
    """經理收場：清場（先禮後兵）→ 自殺。"""
    worker_entries = roster.get_worker_entries()

    # 判定：冇工人 → 直接自殺
    if len(worker_entries) == 0:
        lifecycle.exit_self(reason, exit_code)
        return

    # 先禮：逐個通知自殺（哨兵 None——worker 收到即收工）
    for worker_pid, worker_entry in list(worker_entries.items()):
        _ask_worker_to_exit(worker_pid, worker_entry)

    worker_exit_wait_sec = _get_worker_exit_wait_sec()

    workers_gone = _wait_workers_gone(worker_exit_wait_sec)

    # 判定：9 秒仍有人 → 後兵（直接殺）
    if workers_gone is False:
        _kill_remaining_workers(reason)
        _wait_workers_gone(WORKER_KILL_WAIT_SEC)

        # 清場後再收一次屍（殺死嘅工人：除名＋刪狀態檔）
        job_scheduler.reap_workers()

    lifecycle.exit_self(reason, exit_code)


def _get_worker_exit_wait_sec() -> float:
    """先禮等待秒數（runtime.json manager_exit_wait_sec；缺席＝WORKER_EXIT_WAIT_SEC）。"""
    daemon_conf = context.daemon().get_daemon_conf_data()

    return float(daemon_conf.get("manager_exit_wait_sec", WORKER_EXIT_WAIT_SEC))


def _ask_worker_to_exit(worker_pid: int, worker_entry: dict) -> None:
    """先禮：哨兵（空閒工人即收）＋撤退旗標檔（做單中工人 1 秒內見到）。"""
    try:
        worker_entry["job_queue"].put(None)
    except Exception as exc:  # noqa: BLE001
        # 例外（queue 已關／程序已死——哨兵送唔到唔阻旗標；記一行痕）
        sc_logger.log("worker exit notify failed pid=" + str(worker_pid) + " | " + str(exc))

    get_exit_flag_file(worker_pid).write_text("1", encoding="utf-8")


def get_exit_flag_file(worker_pid: int) -> Path:
    """工人撤退旗標檔（worker.<pid>.exit——檔案式跨平台通知）。"""
    return Path(context.PROCESS_DIR) / (identity.WORKER_PROGRAM_NAME + "." + str(worker_pid) + ".exit")


def _wait_workers_gone(wait_seconds: float) -> bool:
    """逐秒重掃工人名冊（收屍＋重讀）；全走＝True。"""
    elapsed_seconds = 0

    # 逐秒檢查（唔一次過等——要知幾時清場）
    while elapsed_seconds < wait_seconds:
        job_scheduler.reap_workers()

        # 判定：名冊已空 → 清場
        if len(roster.get_worker_entries()) == 0:
            return True

        time.sleep(1.0)
        elapsed_seconds = elapsed_seconds + 1

    return len(roster.get_worker_entries()) == 0


def _kill_remaining_workers(reason: str) -> None:
    """後兵：仍未走嘅工人逐個 terminate（lifecycle.exit_child）。"""
    for worker_pid in list(roster.get_worker_entries().keys()):
        lifecycle.exit_child(worker_pid, reason)

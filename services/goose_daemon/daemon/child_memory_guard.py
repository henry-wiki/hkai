#!/usr/bin/env python3
"""
child_memory_guard.py

worker 監護人——子程序記憶體守衛（child_memory_guard_class singleton——get_instance() 取得）。

職責單一：心跳每拍檢查 worker 子樹（goose 鏈）記憶體總量——超限即中止此訊息：
    1. 收終態——走 msg_processor 單一出口（msg_log 短句「abort: ...」＋error_log 完整記錄）
    2. 記 audit.log（sc_logger——系統級足跡；含全部 pids）
    3. 殺子樹（terminate → 逾時 kill——同 context.exit_with_children 同一手法；worker 自己唔死）

動機：goose 喺「等卡死請求」路徑有記憶體狂泄 bug（2026-10-08 實錄：12→20GB／12 分鐘）；
本衛士＝我方兜底——唔改 goose 本體，只喺失控時及早中止（上限＝manifest goose_daemon.child_memory_limit_mb——缺＝默認 CHILD_MEMORY_LIMIT_MB）。
"""

import os

import psutil

from ..msg.msg_events import EVENT_MEMORY_GUARD_ABORT
from ..runtime import sc_logger

# 子樹記憶體上限默認值（MB——goose_daemon.child_memory_limit_mb 覆寫；正常 run 遠低於此，失控案例 12~20GB 級）
CHILD_MEMORY_LIMIT_MB = 3 * 1024

# 子樹終止寬限（秒——terminate 後等幾久先硬殺）
CHILD_TERMINATE_GRACE_SEC = 5


class child_memory_guard_class:
    """子程序記憶體守衛（singleton——worker 程序內共享）。"""

    _instance: "child_memory_guard_class | None" = None

    def __init__(self) -> None:
        # 本次工作已開火旗標（防重複中止——冇子程序時自動復位）
        self._abort_fired = False

    @classmethod
    def get_instance(cls) -> "child_memory_guard_class":
        """取得 child_memory_guard_class 單例。"""
        if cls._instance is None:
            cls._instance = cls()
        return cls._instance

    # 心跳每拍呼叫（worker.py 的 _beat_loop）：檢查子樹記憶體——超限即中止
    def check(self) -> None:
        """檢查子樹記憶體總量（冇子程序＝唔動作＋復位旗標）。"""
        descendant_processes = self._get_descendant_processes()

        # 判定：冇子程序（閒置／工單已完）→ 復位旗標、返回
        if len(descendant_processes) == 0:
            self._abort_fired = False
            return

        # 判定：本次已開火（等子樹清空先復位）→ 唔重複
        if self._abort_fired:
            return

        total_rss_mb = self._sum_rss_mb(descendant_processes)
        memory_limit_mb = self._resolve_memory_limit_mb()

        # 判定：未超限 → 正常返回
        if total_rss_mb <= memory_limit_mb:
            return

        self._abort_fired = True
        self._abort_over_limit(descendant_processes, total_rss_mb, memory_limit_mb)

    # 上限查詢（manifest：goose_daemon.child_memory_limit_mb——缺＝默認常數）
    def _resolve_memory_limit_mb(self) -> int:
        from ..context import daemon

        daemon_conf_data = daemon().get_daemon_conf_data()
        return int(daemon_conf_data.get("daemon_child_memory_limit_mb", CHILD_MEMORY_LIMIT_MB))

    # 超限中止：① 收終態（僅未完結——單一出口）② 記 audit ③ 殺子樹
    def _abort_over_limit(self, descendant_processes: list, total_rss_mb: float, memory_limit_mb: int) -> None:
        from ..context import msg_main, msg_processor, msg_state_machine

        total_rss_gb = total_rss_mb / 1024.0
        abort_message = "abort: child memory over limit — rss %.2fGB > %dMB (subtree killed)" % (total_rss_gb, memory_limit_mb)

        # 判定：訊息仍未完結（故事未收）→ 走單一出口收終態（msg_log＋error_log 一併記）
        if not msg_state_machine().is_terminal_status(msg_main().get_msg_status()):
            msg_processor()._transition_status(EVENT_MEMORY_GUARD_ABORT, abort_message)

        # audit.log（系統級足跡——殺樹必記：值＋全部 pids）
        descendant_pids = [child_process.pid for child_process in descendant_processes]
        sc_logger.log("memory guard: rss %.2fGB over %dMB — subtree killed, pids=%s" % (total_rss_gb, memory_limit_mb, descendant_pids))

        # 殺子樹（記憶體即刻釋放——交返主流程（join 返回）行正常收尾）
        self._kill_descendant_tree(descendant_processes)

    # 殺整個子樹（先客氣 terminate → 逾時硬殺——只落子孫、唔掂自己）
    def _kill_descendant_tree(self, descendant_processes: list) -> None:
        for child_process in descendant_processes:
            try:
                child_process.terminate()
            except psutil.Error:
                pass

        _gone_processes, alive_processes = psutil.wait_procs(descendant_processes, timeout=CHILD_TERMINATE_GRACE_SEC)

        for child_process in alive_processes:
            try:
                child_process.kill()
            except psutil.Error:
                pass

    # worker 全部子孫程序（遞歸——只向下；查唔到＝空清單）
    def _get_descendant_processes(self) -> list:
        try:
            return psutil.Process(os.getpid()).children(recursive=True)
        except psutil.Error:
            return []

    # 子孫記憶體總量（RSS 累加——MB；讀唔到（已死）當 0）
    def _sum_rss_mb(self, descendant_processes: list) -> float:
        total_rss_mb = 0.0

        for child_process in descendant_processes:
            try:
                total_rss_mb = total_rss_mb + child_process.memory_info().rss / (1024 * 1024)
            except psutil.Error:
                continue

        return total_rss_mb

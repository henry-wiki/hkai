#!/usr/bin/env python3
"""
memory_guard.py

worker 監護人——子程序記憶體守衛（memory_guard singleton——get_instance() 取得）。

職責：心跳每拍檢查 worker 子樹（goose 鏈）記憶體總量，分三級處理：
    1. 超過警告門檻（3 GB——daemon_config.CHILD_MEMORY_WARNING_LIMIT_MB）→ 每拍記 WARNING（audit.log——持續留痕；回落即記一次 normal）
    2. 警告區內 30 秒增長 ≥ 512 MB → 判泄漏 → 中止此訊息
    3. 超過硬上限（6 GB）→ 無論如何立即中止此訊息

中止（2／3）＝ ①收終態（job_executor 單一出口——message_log＋error_log）②記 audit ③殺子樹（worker 自己唔死）。

動機：goose 喺「等卡死請求」路徑有記憶體狂泄 bug（2026-10-08 實錄：12→20GB／12 分鐘）；
本衛士＝我方兜底——唔改 goose 本體，只喺失控時及早中止。
"""

import threading
import time

import psutil

from ..context import job_executor, message_status, message_store
from ..daemon import daemon_config
from ..runtime import process, sc_logger
from .message_events import EVENT_MEMORY_GUARD_ABORT


class memory_guard:
    """子程序記憶體守衛（singleton——worker 程序內共享）。"""

    _instance: "memory_guard | None" = None

    def __init__(self) -> None:
        # 本次工作已開火旗標（防重複中止——冇子程序時自動復位）
        self._abort_fired = False

        # 是否處於警告區（回落時記一次 normal——唔重複）
        self._warn_active = False

        # 記憶體取樣（(時間, MB)——窗口內判斷增長率）
        self._samples: list[tuple[float, float]] = []

    @classmethod
    def get_instance(cls) -> "memory_guard":
        """取得 memory_guard 單例。"""
        if cls._instance is None:
            cls._instance = cls()
        return cls._instance

    # 背景 thread 每拍呼叫（runner.py 啟動）：分級檢查子樹記憶體
    def check(self) -> None:
        """檢查子樹記憶體（冇子程序＝復位；警告區＝留痕；泄漏／硬上限＝中止）。"""
        descendant_processes = self._get_descendant_processes()

        # 判定：冇子程序（閒置／工單已完）→ 復位全部狀態、返回
        if len(descendant_processes) == 0:
            self._reset_state()
            return

        # 判定：本次已開火（等子樹清空先復位）→ 唔重複
        if self._abort_fired:
            return

        total_rss_mb = self._sum_rss_mb(descendant_processes)
        self._record_sample(total_rss_mb)

        memory_warn_limit_mb = daemon_config.CHILD_MEMORY_WARNING_LIMIT_MB

        # 判定：已達硬上限（6 GB）→ 中止（唔理增長率）
        if total_rss_mb >= daemon_config.CHILD_MEMORY_ABSOLUTE_LIMIT_MB:
            self._abort("absolute limit", descendant_processes, total_rss_mb, daemon_config.CHILD_MEMORY_ABSOLUTE_LIMIT_MB)
            return

        # 判定：未達警告門檻 → 正常（如剛由警告區回落，記一次）
        if total_rss_mb <= memory_warn_limit_mb:
            self._log_back_to_normal(total_rss_mb, memory_warn_limit_mb)
            return

        # 警告區：每拍記 WARNING（持續留痕）
        self._warn_active = True
        sc_logger.log("memory guard: warning rss %.2fGB > %dMB" % (total_rss_mb / 1024.0, memory_warn_limit_mb))

        growth_mb = self._growth_mb()
        # 判定：窗口增長達門檻 → 判泄漏 → 中止
        if growth_mb >= daemon_config.CHILD_MEMORY_GROWTH_LIMIT_MB:
            self._abort("leak suspected (growth %dMB/%ds)" % (growth_mb, daemon_config.CHILD_MEMORY_GROWTH_WINDOW_SEC), descendant_processes, total_rss_mb, memory_warn_limit_mb)

    # 復位（冇子程序時——下一次工單由零開始）
    def _reset_state(self) -> None:
        self._abort_fired = False
        self._warn_active = False
        self._samples = []

    # 回落正常（警告區 → 正常；只記一次）
    def _log_back_to_normal(self, total_rss_mb: float, memory_warn_limit_mb: int) -> None:
        # 判定：本來唔在警告區 → 唔記
        if not self._warn_active:
            return

        self._warn_active = False
        sc_logger.log("memory guard: back to normal rss %.2fGB <= %dMB" % (total_rss_mb / 1024.0, memory_warn_limit_mb))

    # 記錄取樣（只留窗口內樣本——供增長率計算）
    def _record_sample(self, total_rss_mb: float) -> None:
        now = time.time()
        self._samples.append((now, total_rss_mb))

        max_age_sec = daemon_config.CHILD_MEMORY_GROWTH_WINDOW_SEC
        self._samples = [sample for sample in self._samples if now - sample[0] <= max_age_sec]

    # 窗口增長（MB——最早樣本至現在；樣本不足＝0）
    def _growth_mb(self) -> float:
        # 判定：未夠兩個樣本 → 無法計算
        if len(self._samples) < 2:
            return 0.0

        oldest_rss_mb = self._samples[0][1]
        newest_rss_mb = self._samples[-1][1]
        return newest_rss_mb - oldest_rss_mb

    # 中止：① 收終態 ② 記 audit ③ 殺子樹
    def _abort(self, reason: str, descendant_processes: list, total_rss_mb: float, limit_mb: int) -> None:
        self._abort_fired = True
        total_rss_gb = total_rss_mb / 1024.0
        abort_message = "abort: %s — rss %.2fGB (limit %dMB; subtree killed)" % (reason, total_rss_gb, limit_mb)

        # 判定：訊息仍未完結（故事未收）→ 走單一出口收終態（message_log＋error_log 一併記）
        if not message_status().is_terminal_status(message_store().get_msg_status()):
            job_executor()._transition_status(EVENT_MEMORY_GUARD_ABORT, abort_message)

        # audit.log（系統級足跡——殺樹必記：原因＋值＋全部 pids）
        descendant_pids = [child_process.pid for child_process in descendant_processes]
        sc_logger.log("memory guard: %s — rss %.2fGB, pids=%s killed" % (reason, total_rss_gb, descendant_pids))

        # 殺子樹（記憶體即刻釋放——交返主流程（join 返回）行正常收尾）
        self._kill_descendant_tree(descendant_processes)

    # 殺整個子樹（直接 kill——記憶體即時釋放；只落子孫、唔掂自己）
    def _kill_descendant_tree(self, descendant_processes: list) -> None:
        for child_process in descendant_processes:
            try:
                child_process.kill()
            except psutil.Error:
                pass

        sc_logger.log("memory guard: subtree killed (pids=%d)" % len(descendant_processes))

    # worker 全部子孫程序（遞歸——只向下；查唔到＝空清單）
    def _get_descendant_processes(self) -> list:
        try:
            return psutil.Process(process.self_pid()).children(recursive=True)
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


# 守衛節奏（秒——同心跳同拍）
GUARD_INTERVAL_SEC = 1.0


def start() -> None:
    """開背景記憶體守衛 thread（每拍 check；由 worker 啟動時呼叫）。"""
    memory_guard_thread = threading.Thread(
        target=_monitor_loop,
        daemon=True,
        name="memory_guard",
    )
    memory_guard_thread.start()


def _monitor_loop() -> None:
    """守衛迴圈（每拍檢查子樹記憶體；超限即中止）。"""
    while True:
        memory_guard.get_instance().check()
        time.sleep(GUARD_INTERVAL_SEC)

#!/usr/bin/env python3
"""
sc_logger.py

    共用執行日誌（sc_logger_class singleton——setup()／log()；daemon＋acp 子程序共用同一份）。

    兩個檔（全部在 aider_daemon/runtime/）：
        error.log       異常／崩潰（sys.excepthook 完整 traceback）
        audit.log       一般記錄（啟動足跡／log()／print 重定向——append-only，永不修改）

    stdout 重定向（協定安全——acp 子程序嘅 stdout 係 ACP 通道）：
        setup() 先把真 stdout 嘅 .buffer 收埋，再將 sys.stdout 換成 _print_logger_class；
        stray print → audit.log；協定層搵 sys.stdout.buffer → 原封 passthrough（通道唔斷）。
        sys.stdin 唔碰（協定輸入通道）。

    拉起期（logger 接管前）失敗由 main.py 以 stdlib 直寫 error.log（防線）；輸出丟棄（PHP 端 NUL）。

    依賴：只 import aider_daemon/__init__.py 的 RUNTIME_DIR（純路徑常數——不會失敗）。

    使用方式（各 main.py 最頂部——主程序載入前）：
        from .runtime import sc_logger        # daemon（aider_daemon/main.py）
        from ..runtime import sc_logger       # acp（aider_daemon/acp/main.py）
        sc_logger.setup()
"""

import atexit
import logging
import sys
from logging.handlers import RotatingFileHandler
from pathlib import Path

from .. import RUNTIME_DIR

# log 格式常數
LOG_FORMAT    = "%(asctime)s [%(levelname)s] (Line:%(lineno)d) : %(message)s"
LOG_DATEFMT   = "%Y-%m-%d %H:%M:%S"
# 單檔上限 5 MiB（達上限即輪替——現行檔永遠 < 此值）
LOG_MAX_BYTES = 5 * 1024 * 1024
# 備份份數 3（不含現行檔；命名 X.log.1／.2／.3——最舊者於下次輪替被刪）
LOG_BACKUP_COUNT = 3


class sc_logger_class:
    """
    ACP server 執行日誌（singleton——整個程序只有一個實例）。

    職責單一：兩個檔（error／audit）的 RotatingFileHandler 寫入；
    一次 setup() 完成設定，之後任何程序可直接 log()。
    """

    _instance: "sc_logger_class | None" = None
    # 一次性設定旗標（setup 可安全重複呼叫——只生效一次）
    _setup_done: bool = False

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "sc_logger_class":
        """取得 sc_logger_class 單例（整個程序共用同一實例）。"""
        return cls()

    # 建立單一 RotatingFileHandler logger（5MB×3——硬碟防爆；重複呼叫不疊加 handler）
    @staticmethod
    def _build_logger(logger_name: str, log_file_name: str) -> logging.Logger:
        logger = logging.getLogger(logger_name)
        logger.setLevel(logging.INFO)
        logger.propagate = False

        # 已有 handler → 不重複疊加（setup 可安全重複呼叫）
        if logger.handlers:
            return logger

        log_path = RUNTIME_DIR / log_file_name
        log_path.parent.mkdir(parents=True, exist_ok=True)

        handler = RotatingFileHandler(
            str(log_path), maxBytes=LOG_MAX_BYTES, backupCount=LOG_BACKUP_COUNT, encoding="utf-8"
        )
        handler.setFormatter(logging.Formatter(LOG_FORMAT, datefmt=LOG_DATEFMT))
        logger.addHandler(handler)

        return logger

    # 全域未捕捉例外 → error.log（KeyboardInterrupt 交回預設行為）
    def _global_exception_handler(self, exc_type, exc_value, exc_traceback) -> None:
        # Ctrl+C 等中斷 → 走 Python 預設（不視為崩潰）
        if issubclass(exc_type, KeyboardInterrupt):
            sys.__excepthook__(exc_type, exc_value, exc_traceback)
            return

        self._build_logger("runtime.error", "error.log").critical(
            "unhandled exception", exc_info=(exc_type, exc_value, exc_traceback)
        )

    # 程序退出 → audit.log（正常或非正常退出皆觸發）
    def _exit_handler(self) -> None:
        self._build_logger("runtime.audit", "audit.log").info("process exited")

    # 一次性日誌設定（main.py 最頂部呼叫——主程序載入前完成）
    def setup(self) -> None:
        # 已設定過 → 直接返回（可安全重複呼叫）
        if self._setup_done:
            return
        self._setup_done = True

        # 兩個檔（各自 RotatingFileHandler 5MB×3——error 先開，崩潰通道優先）
        self._build_logger("runtime.error", "error.log")
        audit_logger = self._build_logger("runtime.audit", "audit.log")

        # 真 stdout buffer 先收埋（協定層 passthrough——見檔頭「stdout 重定向」）
        real_stdout_buffer = sys.stdout.buffer

        # 全域攔截：未捕捉例外 → error.log；程序退出 → audit.log；print → audit.log
        sys.excepthook = self._global_exception_handler
        atexit.register(self._exit_handler)
        sys.stdout = _print_logger_class(real_stdout_buffer)

        # 啟動足跡（第一個記錄點——此刻起任何崩潰都會留下痕跡）
        audit_logger.info("runtime setup done")
        audit_logger.info("python %s", sys.version.replace("\n", " "))
        audit_logger.info("argv: %s", sys.argv)
        audit_logger.info("cwd: %s", Path.cwd())

    # 寫入 audit.log（一般記錄——append-only；任何程序皆可直接呼叫）
    def log(self, message: str) -> None:
        self._build_logger("runtime.audit", "audit.log").info(message)


# sys.stdout 重定向載體（print 不漏；.buffer＝真 stdout——ACP 協定通道 passthrough）
class _print_logger_class:
    """sys.stdout 重定向 → audit.log（print 不漏）；.buffer passthrough（協定唔斷）。"""

    # 文字串流介面（呼叫方會讀 encoding／isatty——缺任一即 AttributeError 崩潰）
    encoding = "utf-8"
    errors   = "replace"

    def __init__(self, real_stdout_buffer) -> None:
        self._real_stdout_buffer = real_stdout_buffer

    # 協定層通道（acp stdio transport 用 sys.stdout.buffer 寫 JSON-RPC——原封 passthrough）
    @property
    def buffer(self):
        return self._real_stdout_buffer

    def write(self, message) -> None:
        # 空行不記（只留有意義的輸出）
        if message.strip():
            sc_logger_class.get_instance().log(f"[print] {message.strip()}")

    def flush(self) -> None:
        pass

    def isatty(self) -> bool:
        return False

    def fileno(self) -> int:
        return 1


# ===== bridging（模組級——供 main.py／daemon_manager／aider_entry／core 直接呼叫）=====
# 一次性日誌設定（main.py 最頂部呼叫——主程序載入前完成）
def setup() -> None:
    return sc_logger_class.get_instance().setup()


# 寫入 audit.log（一般記錄——append-only；任何程序皆可直接呼叫）
def log(message: str) -> None:
    return sc_logger_class.get_instance().log(message)

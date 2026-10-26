#!/usr/bin/env python3
"""
sc_logger.py

    daemon 執行日誌（模組級函數——無單例；setup()／log()）。

    兩個檔（全部在 <站台>/data/logs/）：
        goose_daemon.error.log   異常／崩潰（sys.excepthook 完整 traceback）
        goose_daemon.audit.log   一般記錄（啟動足跡／log()／print 重定向——append-only，永不修改）

    拉起期（logger 接管前）失敗由 write_early_error_log() 以 stdlib 直寫 error.log（main.py 呼叫）；輸出丟棄（PHP 端 NUL）。

    依賴：goose_daemon/__init__.py 的 DATA_DIR（純路徑常數——不會失敗）。
    其他 py 檔不需 import 本模組（例外／print 由本模組全域攔截）。

    使用方式（main.py 最頂部——主程序載入前）：
        from .runtime import sc_logger
        sc_logger.setup()
"""

import atexit
import logging
import sys
from logging.handlers import RotatingFileHandler
from pathlib import Path
from types import TracebackType

from .. import DATA_DIR
from . import identity

# log 格式常數
LOG_FORMAT    = "%(asctime)s [%(levelname)s] (Line:%(lineno)d) : %(message)s"
LOG_DATEFMT   = "%Y-%m-%d %H:%M:%S"
# 單檔上限 5 MiB（達上限即輪替——現行檔永遠 < 此值）
LOG_MAX_BYTES = 5 * 1024 * 1024
# 備份份數 3（不含現行檔；命名 X.log.1／.2／.3——最舊者於下次輪替被刪）
# → 每個 log 共 4 檔、上限約 20 MiB（5 MiB × 4）；本模組 2 個 log 合計上限約 40 MiB
LOG_BACKUP_COUNT = 3

# 日誌目錄（站台 data/logs——程式碼目錄唔放執行產物）
LOG_DIR = DATA_DIR / "logs"

# 程式名（單一來源——identity；檔名／logger 名皆由此砌）
PROGRAM_NAME = identity.DAEMON_PROGRAM_NAME

# 日誌檔（全部路徑集中喺呢度——唔喺函數內砌）
ERROR_LOG_PATH = LOG_DIR / (PROGRAM_NAME + ".error.log")
AUDIT_LOG_PATH = LOG_DIR / (PROGRAM_NAME + ".audit.log")

# logger 名（logging.getLogger 用——同檔名同源）
ERROR_LOGGER_NAME = PROGRAM_NAME + ".error"
AUDIT_LOGGER_NAME = PROGRAM_NAME + ".audit"

# 一次性設定旗標（setup 可安全重複呼叫——只生效一次）
_setup_done = False


def _build_logger(logger_name: str, log_path: Path) -> logging.Logger:
    """建立單一 RotatingFileHandler logger（5MB×3——硬碟防爆；重複呼叫不疊加 handler）。"""
    logger = logging.getLogger(logger_name)
    logger.setLevel(logging.INFO)
    logger.propagate = False

    # 已有 handler → 不重複疊加（setup 可安全重複呼叫）
    if logger.handlers:
        return logger

    log_path.parent.mkdir(parents=True, exist_ok=True)

    handler = RotatingFileHandler(
        str(log_path), maxBytes=LOG_MAX_BYTES, backupCount=LOG_BACKUP_COUNT, encoding="utf-8"
    )
    handler.setFormatter(logging.Formatter(LOG_FORMAT, datefmt=LOG_DATEFMT))
    logger.addHandler(handler)

    return logger


def _global_exception_handler(
    exc_type: type[BaseException],
    exc_value: BaseException,
    exc_traceback: TracebackType | None,
) -> None:
    """全域未捕捉例外 → error.log（KeyboardInterrupt 交回預設行為）。"""
    # Ctrl+C 等中斷 → 走 Python 預設（不視為崩潰）
    if issubclass(exc_type, KeyboardInterrupt):
        sys.__excepthook__(exc_type, exc_value, exc_traceback)
        return

    _ = _build_logger(ERROR_LOGGER_NAME, ERROR_LOG_PATH).critical(
        "unhandled exception", exc_info=(exc_type, exc_value, exc_traceback)
    )


def _exit_handler() -> None:
    """程序退出 → audit.log（正常或非正常退出皆觸發）。"""
    _ = _build_logger(AUDIT_LOGGER_NAME, AUDIT_LOG_PATH).info("daemon process exited")


def setup() -> None:
    """一次性日誌設定（main.py 最頂部呼叫——主程序載入前完成）。"""
    global _setup_done

    # 已設定過 → 直接返回（可安全重複呼叫）
    if _setup_done is True:
        return

    _setup_done = True

    # 兩個檔（各自 RotatingFileHandler 5MB×3——error 先開，崩潰通道優先）
    _ = _build_logger(ERROR_LOGGER_NAME, ERROR_LOG_PATH)
    audit_logger = _build_logger(AUDIT_LOGGER_NAME, AUDIT_LOG_PATH)

    # 全域攔截：未捕捉例外 → error.log；程序退出 → audit.log；print → audit.log
    sys.excepthook = _global_exception_handler
    _ = atexit.register(_exit_handler)
    sys.stdout = _stdout_audit_writer()

    # 啟動足跡（第一個記錄點——此刻起任何崩潰都會留下痕跡）
    audit_logger.info("runtime setup done")
    audit_logger.info("python %s", sys.version.replace("\n", " "))
    audit_logger.info("argv: %s", sys.argv)
    audit_logger.info("cwd: %s", Path.cwd())


def log(message: str) -> None:
    """寫入 audit.log（一般記錄——append-only；任何程序皆可直接呼叫）。"""
    _ = _build_logger(AUDIT_LOGGER_NAME, AUDIT_LOG_PATH).info(message)


def write_early_error_log() -> None:
    """setup 前／失敗時直寫 error.log（stdlib——logger 未接管嘅最後防線）。"""
    try:
        import traceback

        ERROR_LOG_PATH.parent.mkdir(parents=True, exist_ok=True)

        with open(ERROR_LOG_PATH, "a", encoding="utf-8") as error_log_handle:
            _ = error_log_handle.write(traceback.format_exc())
    except Exception:  # noqa: BLE001, S110
        pass  # 記錄失敗不可蓋過原始錯誤（呼叫端保留原 traceback）


# sys.stdout 重定向載體（print 不漏——需 write/flush 介面，獨立小 class）
class _stdout_audit_writer:
    """sys.stdout 重定向 → audit.log（print 不漏）。"""

    # 文字串流介面（呼叫方會讀 encoding／isatty——缺任一即 AttributeError 崩潰）
    encoding: str = "utf-8"
    errors: str   = "replace"

    def write(self, message: str) -> None:
        """print 內容 → audit.log（空行不記——只留有意義輸出）。"""
        # 判定：空白內容 → 唔記
        if message.strip() == "":
            return

        log("[print] " + message.strip())

    def flush(self) -> None:
        pass

    def isatty(self) -> bool:
        return False

    def fileno(self) -> int:
        return -1

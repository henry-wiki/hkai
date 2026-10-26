#!/usr/bin/env python3
"""
sc_logger.py

    bridge 執行日誌（sc_logger_class singleton——setup()／log()；handler 由 LOG_CONFIG 一次建立）。

    兩個檔（全部在 data/logs/）：
        bridge.error.log   異常／崩潰（sys.excepthook 完整 traceback）
        bridge.audit.log   一般記錄（啟動足跡／log()／print 重定向——append-only，永不修改）

    拉起期（logger 接管前）失敗由 main.py 以 stdlib 直寫 error.log（防線）；輸出丟棄（PHP 端 NUL）。

    依賴：只 import bridge/__init__.py 的 DATA_DIR（純路徑常數——不會失敗）。
    其他 py 檔不需 import 本模組（例外／print 由本模組全域攔截）。

    使用方式（main.py 最頂部——主程序載入前）：
        from .runtime import sc_logger
        sc_logger.setup()
"""

import atexit
import logging
import logging.config
import sys
from pathlib import Path
from types import TracebackType

from .. import DATA_DIR

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

# 完整日誌配置（logging.config.dictConfig——單一擁有者：runtime.* ＋ uvicorn.*）
# uvicorn.run(log_config=None) 唔會覆蓋；全部 logger 由呢份 config 定義
LOG_CONFIG = {
    "version": 1,
    # 保留已存在 logger（唔准 dictConfig 關掉 runtime.*）
    "disable_existing_loggers": False,
    "formatters": {
        "standard": {
            "format": LOG_FORMAT,
            "datefmt": LOG_DATEFMT,
        },
    },
    "handlers": {
        "audit_file": {
            "class": "logging.handlers.RotatingFileHandler",
            "filename": str(LOG_DIR / "bridge.audit.log"),
            "maxBytes": LOG_MAX_BYTES,
            "backupCount": LOG_BACKUP_COUNT,
            "encoding": "utf-8",
            "formatter": "standard",
        },
        "error_file": {
            "class": "logging.handlers.RotatingFileHandler",
            "filename": str(LOG_DIR / "bridge.error.log"),
            "maxBytes": LOG_MAX_BYTES,
            "backupCount": LOG_BACKUP_COUNT,
            "encoding": "utf-8",
            "formatter": "standard",
            "level": "ERROR",
        },
    },
    "loggers": {
        "runtime.audit":   {"handlers": ["audit_file", "error_file"], "level": "INFO", "propagate": False},
        "runtime.error":   {"handlers": ["audit_file", "error_file"], "level": "INFO", "propagate": False},
        "uvicorn":         {"handlers": ["audit_file", "error_file"], "level": "INFO", "propagate": False},
        "uvicorn.error":   {"handlers": ["audit_file", "error_file"], "level": "INFO", "propagate": False},
        "uvicorn.access":  {"handlers": ["audit_file"],               "level": "INFO", "propagate": False},
    },
}


class sc_logger_class:
    """
    bridge 執行日誌（singleton——整個程序只有一個實例）。

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

    # 取得 logger（handler 由 LOG_CONFIG 一次建立——唔再逐個手動加）
    @staticmethod
    def _get_logger(logger_name: str) -> logging.Logger:
        return logging.getLogger(logger_name)

    # 全域未捕捉例外 → error.log（KeyboardInterrupt 交回預設行為）
    def _global_exception_handler(self, exc_type: type[BaseException], exc_value: BaseException, exc_traceback: TracebackType | None) -> None:
        # Ctrl+C 等中斷 → 走 Python 預設（不視為崩潰）
        if issubclass(exc_type, KeyboardInterrupt):
            sys.__excepthook__(exc_type, exc_value, exc_traceback)
            return

        self._get_logger("runtime.error").critical(
            "unhandled exception", exc_info=(exc_type, exc_value, exc_traceback)
        )

    # 程序退出 → audit.log（正常或非正常退出皆觸發）
    def _exit_handler(self) -> None:
        self._get_logger("runtime.audit").info("server process exited")

    # 一次性日誌設定（main.py 最頂部呼叫——主程序載入前完成）
    def setup(self) -> None:
        # 已設定過 → 直接返回（可安全重複呼叫）
        if self._setup_done:
            return
        self._setup_done = True

        # 一次套用完整配置（audit／error／uvicorn 全部 logger——單一擁有者）
        LOG_DIR.mkdir(parents=True, exist_ok=True)
        logging.config.dictConfig(LOG_CONFIG)

        audit_logger = self._get_logger("runtime.audit")

        # 全域攔截：未捕捉例外 → error.log；程序退出 → audit.log；print → audit.log
        sys.excepthook = self._global_exception_handler
        _ = atexit.register(self._exit_handler)
        sys.stdout = _print_logger_class()

        # 啟動足跡（第一個記錄點——此刻起任何崩潰都會留下痕跡）
        audit_logger.info("runtime setup done")
        audit_logger.info("python %s", sys.version.replace("\n", " "))
        audit_logger.info("argv: %s", sys.argv)
        audit_logger.info("cwd: %s", Path.cwd())

    # 寫入 audit.log（一般記錄——append-only；任何程序皆可直接呼叫）
    def log(self, message: str) -> None:
        self._get_logger("runtime.audit").info(message)


# sys.stdout 重定向載體（print 不漏——需 write/flush 介面，獨立小 class）
class _print_logger_class:
    """sys.stdout 重定向 → audit.log（print 不漏）。"""

    def write(self, message: str) -> None:
        # 空行不記（只留有意義的輸出）
        if message.strip():
            sc_logger_class.get_instance().log(f"[print] {message.strip()}")

    def flush(self) -> None:
        pass


# ===== bridging（模組級——供 main.py 直接呼叫）=====
# 一次性日誌設定（main.py 最頂部呼叫——主程序載入前完成）
def setup() -> None:
    return sc_logger_class.get_instance().setup()


# 寫入 audit.log（一般記錄——append-only；任何程序皆可直接呼叫）
def log(message: str) -> None:
    return sc_logger_class.get_instance().log(message)

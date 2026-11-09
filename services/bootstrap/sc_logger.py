#!/usr/bin/env python3
"""sc_logger.py — 全 Python 程序唯一執行日誌（一套實作，所有程序共用）。

職責：
- setup(program_name, extra_loggers=())  一次性設定：<data>/logs/<program>.{audit,error}.log、接管 excepthook／atexit／stdout
- log(message)             一般記錄 → audit.log
- error(message)           錯誤記錄 → error.log（同時 audit）
- get_logger(logger_name)  標準 logger（模組／第三方用，例如 uvicorn）
- write_early_error_log(program_name, error_text)  import 期崩潰用（零依賴）
- rotate_if_needed(log_path)  結構化事件 log 輪替（image_extractor 用）

檔名契約：<program>.audit.log／<program>.error.log——PHP 有讀（bridge_log.php:31），唔可以改名。
規則：只有主程序 setup；worker 唔另開 handler（多進程同檔輪替會丟行）。
上游：各程序 main.py（最頂部 setup）
下游：logging／atexit／sys（stdlib）；bootstrap/path_resolve（資料根——setup 期才 import）
"""

import atexit
import logging
import sys
import traceback
from logging.handlers import RotatingFileHandler
from pathlib import Path
from types import TracebackType

# log 格式常數（全專案一致）
LOG_FORMAT = "%(asctime)s [%(levelname)s] (Line:%(lineno)d) : %(message)s"
LOG_DATEFMT = "%Y-%m-%d %H:%M:%S"
# 單檔上限 5 MiB（達上限即輪替——現行檔永遠 < 此值）
LOG_MAX_BYTES = 5 * 1024 * 1024
# 備份份數 3（不含現行檔；命名 X.log.1／.2／.3——最舊者於下次輪替被刪）
LOG_BACKUP_COUNT = 3

# 一次性設定旗標（setup 可安全重複呼叫——只生效一次）
_setup_done = False

# 兩個程式級 logger（setup 後才有值；log()／error() 用）
_audit_logger: logging.Logger | None = None
_error_logger: logging.Logger | None = None


def setup(program_name: str, extra_loggers: tuple[str, ...] = (), capture_stdout: bool = False) -> None:
    """一次性日誌設定（程序入口最頂部呼叫；重複呼叫無效）。

    program_name＝程式名（同時決定檔名 <program>.audit.log／<program>.error.log）。
    extra_loggers＝額外接管嘅 logger 名（例：bridge 傳 ("uvicorn", "uvicorn.error", "uvicorn.access")）。
    capture_stdout＝True 才重定向 sys.stdout（只限 stdout 唔係協議嘅程序；CLI 一律 False）。
    """
    global _setup_done, _audit_logger, _error_logger

    # 判定：已設定 → 直接返回（唔重複加 handler／hook）
    if _setup_done:
        return

    _setup_done = True

    log_dir = _resolve_log_dir()
    log_dir.mkdir(parents=True, exist_ok=True)

    audit_handler = _build_file_handler(log_dir / (program_name + ".audit.log"), logging.INFO)
    error_handler = _build_file_handler(log_dir / (program_name + ".error.log"), logging.ERROR)

    shared_handlers = (audit_handler, error_handler)

    _audit_logger = _build_logger(program_name + ".audit", shared_handlers)
    _error_logger = _build_logger(program_name + ".error", shared_handlers)

    # 逐個接管額外 logger（第三方／框架——同一個 handler，唔另開檔）
    for extra_logger_name in extra_loggers:
        _ = _build_logger(extra_logger_name, shared_handlers)

    # 全域攔截：未捕捉例外 → error.log；程序退出 → audit.log
    sys.excepthook = _global_exception_handler
    _ = atexit.register(_exit_handler)

    # 判定：程式允許接管 stdout → print 一併寫入 audit.log（CLI 不可以——stdout 係協議）
    if capture_stdout:
        sys.stdout = _print_logger_class()

    # 啟動足跡（第一個記錄點——此刻起任何崩潰都會留下痕跡）
    _audit_logger.info("runtime setup done")
    _audit_logger.info("python %s", sys.version.replace("\n", " "))
    _audit_logger.info("argv: %s", sys.argv)
    _audit_logger.info("cwd: %s", Path.cwd())


def log(message: str) -> None:
    """一般記錄 → audit.log（setup 前呼叫＝自動以 "sc_logger" 名義補設定）。"""
    _ensure_setup()

    audit_logger = _get_audit_logger()
    audit_logger.info(message)


def error(message: str) -> None:
    """錯誤記錄 → error.log（同時 audit.log）。"""
    _ensure_setup()

    error_logger = _get_error_logger()
    error_logger.error(message)


def get_logger(logger_name: str) -> logging.Logger:
    """標準 logger（模組用——例如 logging.getLogger(__name__) 嘅等價入口）。"""
    return logging.getLogger(logger_name)


def write_early_error_log(program_name: str, error_text: str) -> None:
    """import 期／setup 前崩潰 → 直寫 <data>/logs/<program>.error.log（零依賴，失敗即放棄）。"""
    log_dir = _resolve_early_log_dir()

    # 判定：資料根解析失敗 → 放棄（唔寫源碼目錄）
    if log_dir is None:
        return

    try:
        log_dir.mkdir(parents=True, exist_ok=True)

        error_log_path = log_dir / (program_name + ".error.log")

        with error_log_path.open("a", encoding="utf-8") as error_log_file:
            error_log_file.write(error_text + "\n")
    except OSError:
        return


def rotate_if_needed(log_path: Path) -> None:
    """結構化事件 log 輪替：達 5 MiB → 改名 .1（舊 .1 直接覆蓋；失敗即放棄）。"""
    # 判定：檔案未存在 → 唔使輪替
    if log_path.is_file() is False:
        return

    # 判定：未達上限 → 唔使輪替
    if log_path.stat().st_size < LOG_MAX_BYTES:
        return

    backup_file = log_path.with_suffix(".log.1")

    try:
        backup_file.unlink(missing_ok=True)
        _ = log_path.replace(backup_file)
    except OSError:
        return


def _resolve_log_dir() -> Path:
    """日誌目錄（<data>/logs——資料根交 path_resolve，三環境自動分）。"""
    from .path_resolve.get_data_dir import get_data_dir

    return Path(get_data_dir()) / "logs"


def _resolve_early_log_dir():
    """日誌目錄（早期崩潰用——資料根解析失敗＝無檔案日誌；源碼目錄零寫入）。"""
    try:
        return _resolve_log_dir()
    except BaseException:
        return None


def _build_file_handler(log_path: Path, handler_level: int) -> logging.Handler:
    """建立單一 RotatingFileHandler（5 MiB×3、UTF-8、統一 format）。"""
    file_handler = RotatingFileHandler(
        str(log_path),
        maxBytes=LOG_MAX_BYTES,
        backupCount=LOG_BACKUP_COUNT,
        encoding="utf-8",
    )
    file_handler.setLevel(handler_level)
    file_handler.setFormatter(logging.Formatter(LOG_FORMAT, datefmt=LOG_DATEFMT))

    return file_handler


def _build_logger(logger_name: str, handlers: tuple[logging.Handler, logging.Handler]) -> logging.Logger:
    """建立／補齊具名 logger（已有 handler 唔重複疊加）。"""
    module_logger = logging.getLogger(logger_name)
    module_logger.setLevel(logging.INFO)
    module_logger.propagate = False

    # 判定：已有 handler → 唔再疊加（idempotent）
    if module_logger.handlers:
        return module_logger

    for shared_handler in handlers:
        module_logger.addHandler(shared_handler)

    return module_logger


def _ensure_setup() -> None:
    """log()／error() 喺 setup 前被呼叫 → 以 "sc_logger" 名義補一次設定。"""
    # 判定：未設定 → 補設定（避免記錄靜靜消失）
    if _setup_done is False:
        setup("sc_logger")


def _get_audit_logger() -> logging.Logger:
    """程式級 audit logger（setup 後必定存在）。"""
    _ensure_setup()

    # 判定：狀態缺失 → 用標準 logger 頂住（理論上不會發生）
    if _audit_logger is None:
        return logging.getLogger("sc_logger.audit")

    return _audit_logger


def _get_error_logger() -> logging.Logger:
    """程式級 error logger（setup 後必定存在）。"""
    _ensure_setup()

    # 判定：狀態缺失 → 用標準 logger 頂住（理論上不會發生）
    if _error_logger is None:
        return logging.getLogger("sc_logger.error")

    return _error_logger


def _global_exception_handler(exc_type: type[BaseException], exc_value: BaseException, exc_traceback: TracebackType | None) -> None:
    """全域未捕捉例外 → error.log（KeyboardInterrupt 交回預設行為）。"""
    # 判定：Ctrl+C 等中斷 → 走 Python 預設（不視為崩潰）
    if issubclass(exc_type, KeyboardInterrupt):
        sys.__excepthook__(exc_type, exc_value, exc_traceback)
        return

    error_logger = _get_error_logger()
    error_logger.critical("unhandled exception", exc_info=(exc_type, exc_value, exc_traceback))

    # 判定：有原始 stderr（pythonw／無 console 時係 None）→ 照樣輸出 traceback（維護 CLI／父程序可見性）
    if sys.__stderr__ is not None:
        traceback.print_exception(exc_type, exc_value, exc_traceback, file=sys.__stderr__)


def _exit_handler() -> None:
    """程序退出 → audit.log（正常或非正常退出皆觸發）。"""
    audit_logger = _get_audit_logger()
    audit_logger.info("process exited")


# sys.stdout 重定向載體（print 不漏——需完整串流介面，缺任一即第三方庫 AttributeError）
class _print_logger_class:
    """sys.stdout 重定向 → audit.log（print 不漏）。"""

    # 文字串流介面（goose／playwright 類會讀 encoding／isatty——缺任一即崩潰）
    encoding = "utf-8"
    errors = "replace"

    def write(self, message: str) -> None:
        # 空行不記（只留有意義的輸出）
        if message.strip():
            log("[print] " + message.strip())

    def flush(self) -> None:
        pass

    def isatty(self) -> bool:
        return False

    def fileno(self) -> int:
        return -1

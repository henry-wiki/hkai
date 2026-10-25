#!/usr/bin/env python3
"""
main.py

openhands_api_daemon 入口（薄——冷啟動與主迴圈調度集中於 daemon_manager_class，本檔只做日誌初始化＋一行呼叫）。

執行方式（由 bridge 拉起）：

    python -m openhands_api_daemon.main（固定讀 <站台>/data/runtime.json）

啟動順序（兩階段）：
    Phase 1  日誌（sc_logger.setup——主程序尚未載入）
    Phase 2  主程序（context 載入 → daemon 冷啟動 → 主迴圈）

安全機制分工：
- PHP 端：escapeshellarg() 防注入；cmd /c start /B 脫離 PHP process tree
- sc_logger.py：sys.excepthook（崩潰不漏）／sys.stdout 重定向（print 不漏）／RotatingFileHandler 5MB×3（硬碟防爆）
"""

# ===== Phase 1：日誌（最頂部——主程序尚未載入，不碰 context）=====
try:
    from .runtime import sc_logger
    sc_logger.setup()
except BaseException:
    # 最早期失敗（sc_logger 未接管）→ stdlib 直寫 openhands_api_daemon/runtime/error.log（路徑由 __file__ 自推——不依賴任何設定）
    try:
        import pathlib
        import traceback

        _error_log_path = pathlib.Path(__file__).resolve().parent / "runtime" / "error.log"
        _error_log_path.parent.mkdir(parents=True, exist_ok=True)
        with open(_error_log_path, "a", encoding="utf-8") as _error_log_handle:
            _error_log_handle.write(traceback.format_exc())
    except Exception:  # noqa: BLE001, S110
        pass  # 最後防線：記錄失敗不可蓋過原始錯誤（下方 raise 保留原 traceback）

    raise

# ===== Phase 2：主程序（日誌已就緒）=====
from . import context
from .runtime import lifecycle, process


# ==========================================
# 主程序
# ==========================================
def main() -> None:
    """主程序邏輯：載入固定 runtime.json 並啟動 Daemon"""
    # 啟動即記錄自己 pid ＋母親 pid＋建立時間
    process.init()

    context.daemon().initialize()
    exit_code = context.daemon().run()
    lifecycle.exit("daemon run ended", exit_code)


if __name__ == "__main__":
    # 未捕捉例外由 runtime.py 的 sys.excepthook 寫入 error.log（含完整 traceback）
    main()

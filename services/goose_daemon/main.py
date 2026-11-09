#!/usr/bin/env python3
"""
main.py

goose_daemon 入口（薄——冷啟動與主迴圈調度集中於 daemon_manager_class，本檔只做日誌初始化＋一行呼叫）。

執行方式（由 bridge 拉起）：

    python -m goose_daemon.main（固定讀 <站台>/data/runtime.json）

啟動順序：
    import     context／runtime（其餘模組之根）
    logger     sc_logger.setup("goose_daemon")（失敗 → write_early_error_log() 再 raise）
    main()     daemon 冷啟動 → 主迴圈

安全機制分工：
- PHP 端：escapeshellarg() 防注入；cmd /c start /B 脫離 PHP process tree
- bootstrap/sc_logger.py：sys.excepthook（崩潰不漏）／sys.stdout 重定向（print 不漏）／RotatingFileHandler 5MiB×3 備份（硬碟防爆）
"""

import traceback

from . import context
from bootstrap import sc_logger
from .runtime import heartbeat, lifecycle, process

# ===== 日誌（sc_logger 接手 stdout／excepthook）=====
try:
    sc_logger.setup("goose_daemon", capture_stdout=True)
except BaseException:
    from bootstrap.sc_logger import write_early_error_log

    write_early_error_log("goose_daemon", traceback.format_exc())
    raise


# ==========================================
# 主程序
# ==========================================
def main() -> None:
    """主程序邏輯：載入固定 runtime.json 並啟動 Daemon"""
    # 啟動即記錄自己 pid ＋母親 pid＋建立時間
    process.init()

    # 清潔工＝daemon 自己嘅心跳檔（入口指名——避免 worker 模組 import 時覆蓋）
    lifecycle.set_heartbeat_cleaner(heartbeat.remove_heartbeat_file)

    context.daemon().initialize()
    exit_code = context.daemon().run()
    lifecycle.exit("daemon run ended", exit_code)


if __name__ == "__main__":
    # 未捕捉例外由 sc_logger.py 的 sys.excepthook 寫入 error.log（含完整 traceback）
    main()

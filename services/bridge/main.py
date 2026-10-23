#!/usr/bin/env python3
"""
main.py — bridge 入口（薄——初始化與啟動集中於 server_class，本檔只做參數處理＋呼叫）。

啟動方式（runtime.json 固定讀 <站台>/data/runtime.json——唔收參數）：

    cd services
    python -m bridge.main

啟動順序（兩階段）：
    Phase 1  日誌（sc_logger.setup——主程序尚未載入，不碰 context）
    Phase 2  主程序（context 載入 → server 初始化 → server 啟動）
"""

import traceback

# ===== Phase 1：日誌（最頂部——主程序尚未載入，不碰 context）=====
try:
    from .runtime import sc_logger
    sc_logger.setup()
except BaseException:
    # 最早期失敗（sc_logger 未接管）→ stdlib 直寫 data/logs/bridge.error.log（路徑由 __file__ 自推——不依賴任何設定）
    try:
        import pathlib
        import traceback

        _error_log_path = pathlib.Path(__file__).resolve().parents[2] / "data" / "logs" / "bridge.error.log"
        _error_log_path.parent.mkdir(parents=True, exist_ok=True)
        with open(_error_log_path, "a", encoding="utf-8") as _error_log_handle:
            _error_log_handle.write(traceback.format_exc())
    except Exception:  # noqa: BLE001, S110
        pass  # 最後防線：記錄失敗不可蓋過原始錯誤（下方 raise 保留原 traceback）

    raise

# ===== Phase 2：主程序（日誌已就緒）=====
from . import context
from .runtime import process


# ==========================================
# 主程序
# ==========================================
def main() -> None:
    """主程序邏輯：處理參數與啟動 Server"""
    # 啟動即記錄自己 pid ＋母親 pid＋建立時間
    process.init()

    context.server().initialize()
    context.server().start()


if __name__ == "__main__":
    try:
        main()
    except BaseException:
        # 任何形式嘅失敗（啟動／運行）→ 記完整 traceback（launcher 丟棄 stderr，呢份係唯一痕跡）
        sc_logger.log("main failed:" + chr(10) + traceback.format_exc())

        raise

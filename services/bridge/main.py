#!/usr/bin/env python3
"""
main.py — bridge 入口（薄——初始化與啟動集中於 server_class，本檔只做參數處理＋呼叫）。

啟動方式（對齊 aider_daemon 的 -m 模式——拉起時傳入 runtime.json 絕對路徑）：

    cd services
    python -m bridge.main <runtime_config_path>

啟動順序（兩階段）：
    Phase 1  日誌（sc_logger.setup——主程序尚未載入，不碰 context）
    Phase 2  主程序（context 載入 → server 初始化 → server 啟動）
"""

import sys
from pathlib import Path

# ===== Phase 1：日誌（最頂部——主程序尚未載入，不碰 context）=====
try:
    from .runtime import sc_logger
    sc_logger.setup()
except BaseException:
    # 最早期失敗（sc_logger 未接管）→ stdlib 直寫 bridge/runtime/error.log（路徑由 __file__ 自推——不依賴任何設定）
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


# ==========================================
# 主程序
# ==========================================
def main() -> None:
    """主程序邏輯：處理參數與啟動 Server"""
    config_path = Path(sys.argv[1]) if len(sys.argv) > 1 else None
    config = context.load_config(config_path)

    # 記錄 runtime.json 絕對路徑（wake aider_daemon 拉起時需要——唯一動態設定來源）
    config["runtime_config_path"] = str(config_path)

    context.server().initialize(config)
    context.server().start()


if __name__ == "__main__":
    main()

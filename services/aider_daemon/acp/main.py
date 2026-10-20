#!/usr/bin/env python3
"""
main.py

aider_daemon.acp 入口（薄）——ACP server 側（stdio）。

執行方式（由 ACP client spawn）：

    python -m aider_daemon.acp.main        # services/ 喺 PYTHONPATH（cwd＝專案根亦可）

啟動順序（兩階段）：
    Phase 1  日誌（sc_logger.setup——stdout 重定向前先保住 .buffer 協定通道）
    Phase 2  asyncio.run(run_agent(agent()))——initialize／session／prompt 全交 core.py

stdout＝ACP 協定通道（嚴禁雜輸出）；stray print／例外 → aider_daemon/runtime/audit.log／error.log。
"""

import asyncio
import sys

# ===== Phase 1：日誌（最頂部——主程序尚未載入，不碰 context）=====
try:
    from ..runtime import sc_logger
    sc_logger.setup()
except BaseException:
    # 最早期失敗（sc_logger 未接管）→ stdlib 直寫 aider_daemon/runtime/error.log（路徑由 __file__ 自推——不依賴任何設定）
    try:
        import pathlib
        import traceback

        _error_log_path = pathlib.Path(__file__).resolve().parents[1] / "runtime" / "error.log"
        _error_log_path.parent.mkdir(parents=True, exist_ok=True)
        with open(_error_log_path, "a", encoding="utf-8") as _error_log_handle:
            _error_log_handle.write(traceback.format_exc())
    except Exception:  # noqa: BLE001, S110
        pass  # 最後防線：記錄失敗不可蓋過原始錯誤（下方 raise 保留原 traceback）

    raise

# ===== Phase 2：主程序（日誌已就緒）=====
from acp import run_agent


def main() -> None:
    """主程序：跑 ACP agent（stdio 長駐——client 收線＝自然完）。"""
    from .context import agent, config

    sc_logger.log("aider_daemon.acp start")
    sc_logger.log("argv: %s" % (sys.argv,))

    config_data = config()
    sc_logger.log("model: %s" % (config_data["model_name"] or "(未設——prompt 一定失敗)"))

    # 主線程預載 scipy（repomap pagerank 用——2026-10-09 實證：非主線程首次載 OpenBLAS
    # 會同 ACP stdio 讀取線程互鎖死〔CRT/loader 鎖〕；主線程未有其他線程時載入＝零競爭）
    # 缺席（環境未裝）＝跳過——networkx 自己會再試
    try:
        import scipy.sparse  # noqa: F401
        sc_logger.log("preload: scipy.sparse ok")
    except ImportError:
        pass

    # 可拆模組（🧩）邊界：litellm_hook 缺席／安裝失敗＝零捕捉回落（唔阻 ACP 主流程）
    try:
        from . import litellm_hook
        litellm_hook.setup()
    except Exception:  # noqa: BLE001
        sc_logger.log("litellm_hook: setup failed")

    # 可拆模組（🧩）邊界：litellm_providers 缺席／註冊失敗＝零捕捉回落（openrouter 原生照行）
    try:
        from . import litellm_providers
        litellm_providers.setup()
    except Exception:  # noqa: BLE001
        sc_logger.log("litellm_providers: setup failed")

    try:
        asyncio.run(run_agent(agent()))
    finally:
        sc_logger.log("aider_daemon.acp end")


if __name__ == "__main__":
    main()

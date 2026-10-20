#!/usr/bin/env python3
"""
bridge 套件——HTTP server + aider_daemon 管理。

    main.py                       入口：純啟動清單（~15 行）

    runtime/                       基礎設施
    ├── sc_logger.py               執行日誌（error／audit）
    └── sc_config.py               全域 JSON 設定讀取

    server/                        HTTP 伺服器
    ├── server.py                  FastAPI app + lifespan + uvicorn 啟動（singleton）
    ├── server_health.py           GET /health + GET /daemon_alive

    daemon/                        aider_daemon 管理
    └── daemon_registry.py         PID 登記＋跨平台存活檢查

    utils/                         共用工具
    ├── io.py                      檔案讀取工具（tail content）
    ├── timer.py                   時間格式化工具
    └── process_helper.py          子程序安全終止工具
"""

import sys
from pathlib import Path

# 套件根目錄（bridge/ 所在位置）
BASE_DIR = Path(__file__).resolve().parent

# runtime 目錄（log 等運行時資料）
RUNTIME_DIR = BASE_DIR / "runtime"

# 動態設定路徑（runtime.json 絕對位置——PHP 拉起時代入 argv[1]；唯一設定來源）
# 缺參數時為 None（由 main.py 明確檢查後退出——此處不可拋錯，否則 logger 未及接管）
RUNTIME_CONFIG_PATH = Path(sys.argv[1]) if len(sys.argv) > 1 else None

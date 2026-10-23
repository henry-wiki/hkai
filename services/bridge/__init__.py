#!/usr/bin/env python3
"""
bridge 套件——HTTP server + aider_daemon 管理。

    main.py                       入口：純啟動清單（~15 行）

    runtime/                       基礎設施
    ├── sc_logger.py               執行日誌（error／audit）
    └── sc_config.py               全域 JSON 設定讀取

    server/                        HTTP 伺服器
    ├── server.py                  FastAPI app + lifespan + uvicorn 啟動（singleton）

    agent_daemon/                  喚醒 agent daemon（wake／registry）
    └── daemon_registry.py         PID 登記＋跨平台存活檢查

    utils/                         共用工具
    ├── io.py                      檔案讀取工具（tail content）
    └── timer.py                   時間格式化工具
"""

from pathlib import Path

# 套件根目錄（bridge/ 所在位置）
BASE_DIR = Path(__file__).resolve().parent

# runtime 目錄（log 等運行時資料）
RUNTIME_DIR = BASE_DIR / "runtime"

# 站台根（web／services 所在——BASE_DIR＝services/bridge/，向上兩層）
ROOT_DIR = BASE_DIR.parent.parent

# 站台資料目錄（站台根 data/——所有執行產物集中喺呢度）
DATA_DIR = ROOT_DIR / "data"

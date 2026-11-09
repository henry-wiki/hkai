#!/usr/bin/env python3
"""
bridge 套件——HTTP server + daemon 管理。

    main.py                       入口：純啟動清單（~15 行）

    runtime/                       基礎設施
    └── （日誌統一在 bootstrap/sc_logger.py）

    （.env／runtime.json／資料根：services/bootstrap/——PHP sc_config 域對應）

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

# 站台根＋資料根（計算收歸 bootstrap/path.py——本檔唔重複推導）
from bootstrap.path import REPO_ROOT as ROOT_DIR
from bootstrap.path_resolve.get_data_dir import get_data_dir

DATA_DIR = Path(get_data_dir())

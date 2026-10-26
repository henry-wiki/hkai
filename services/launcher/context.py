#!/usr/bin/env python3
"""context.py — launcher 路徑常數（單一來源；全部由本檔位置推導，唔靠 cwd）。

職責：
- 定義套件根／services／站台根／data 目錄
- 定義心跳目錄（清道夫掃描目標）

上游：launcher.main（CLI 入口）、launcher.cleanup（清道夫）
下游：pathlib（stdlib）
"""

from pathlib import Path

# 套件根（services/launcher/）
BASE_DIR = Path(__file__).resolve().parent

# services 目錄（launcher 上一層——daemon spawn cwd）
SERVICES_DIR = BASE_DIR.parent

# 站台根（services 上一層）
ROOT_DIR = SERVICES_DIR.parent

# 站台資料目錄
DATA_DIR = ROOT_DIR / "data"

# 心跳目錄（{program}.{pid}.json——清道夫掃描目標）
HEARTBEAT_DIR = DATA_DIR / "process_status"

# 日誌目錄／launcher 日誌（清道夫操作記錄）
LOG_DIR = DATA_DIR / "logs"
LAUNCHER_LOG_FILE = LOG_DIR / "launcher.log"

__all__ = ["BASE_DIR", "DATA_DIR", "HEARTBEAT_DIR", "LAUNCHER_LOG_FILE", "LOG_DIR", "ROOT_DIR", "SERVICES_DIR"]

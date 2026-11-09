#!/usr/bin/env python3
"""context.py — launcher 路徑常數（單一來源；全部由本檔位置推導，唔靠 cwd）。

職責：
- 定義套件根／services／站台根／data 目錄
- 定義心跳目錄（清道夫掃描目標）

上游：launcher.main（CLI 入口）、launcher.cleanup（清道夫）
下游：pathlib（stdlib）
"""

from pathlib import Path

# 共用路徑（計算收歸 bootstrap/path.py——本檔唔重複推導）
from bootstrap.path import REPO_ROOT as ROOT_DIR, SERVICES_DIR
from bootstrap.path_resolve.get_data_dir import get_data_dir

# 套件根（services/launcher/）
BASE_DIR = Path(__file__).resolve().parent

# 站台資料目錄（由 path.get_data_dir() 取——Path 型方便拼接）
DATA_DIR = Path(get_data_dir())

# 心跳目錄（{program}.{pid}.json——清道夫掃描目標）
HEARTBEAT_DIR = DATA_DIR / "process_status"

__all__ = ["BASE_DIR", "DATA_DIR", "HEARTBEAT_DIR", "ROOT_DIR", "SERVICES_DIR"]

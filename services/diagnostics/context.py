#!/usr/bin/env python3
"""context.py — diagnostics 路徑常數（單一來源；全部由本檔位置推導，唔靠 cwd）。

職責：
- 定義套件根／services／站台根／data 目錄
- 定義 AI instructions 產出位置（system_env.md）

上游：diagnostics 各模組（system_env／project_env／git／files）
下游：pathlib（stdlib）
"""

from pathlib import Path

# 共用路徑（計算收歸 bootstrap/path.py——本檔唔重複推導）
from bootstrap.path import REPO_ROOT as ROOT_DIR, SERVICES_DIR
from bootstrap.path_resolve.get_data_dir import get_data_dir

# 套件根（services/diagnostics/）
BASE_DIR = Path(__file__).resolve().parent

# 站台資料目錄（由 path.get_data_dir() 取——Path 型方便拼接）
DATA_DIR = Path(get_data_dir())

# AI instructions 目錄／系統環境檔（bridge／daemon 讀）
INSTRUCTIONS_DIR = DATA_DIR / "instructions"
SYSTEM_ENV_FILE = INSTRUCTIONS_DIR / "system_env.md"

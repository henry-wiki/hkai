#!/usr/bin/env python3
"""context.py — diagnostics 路徑常數（單一來源；全部由本檔位置推導，唔靠 cwd）。

職責：
- 定義套件根／services／站台根／data 目錄
- 定義 AI instructions 產出位置（system_env.md）

上游：diagnostics 各模組（system_env／project_env／git／files）
下游：pathlib（stdlib）
"""

from pathlib import Path

# 套件根（services/diagnostics/）
BASE_DIR = Path(__file__).resolve().parent

# runtime 目錄（log 等運行時資料）
RUNTIME_DIR = BASE_DIR / "runtime"

# services 目錄（bridge／daemon 所在——BASE_DIR 上一層）
SERVICES_DIR = BASE_DIR.parent

# 站台根（web／services 所在）
ROOT_DIR = SERVICES_DIR.parent

# 站台資料目錄（所有執行產物集中喺呢度）
DATA_DIR = ROOT_DIR / "data"

# AI instructions 目錄／系統環境檔（bridge／daemon 讀）
INSTRUCTIONS_DIR = DATA_DIR / "instructions"
SYSTEM_ENV_FILE = INSTRUCTIONS_DIR / "system_env.md"

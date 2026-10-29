#!/usr/bin/env python3
"""
context.py — image_extractor 路徑常數（單一來源；全部由本檔位置推導，唔靠 cwd）。

職責：
- 定義套件根／services／站台根／data 目錄
- 定義 job 目錄、附件目錄、log 檔、OCR 縮圖上限

上游：image_extractor 各模組（main／extractor／box_store／pipeline）
下游：pathlib（stdlib）
"""

from pathlib import Path

# 套件根（services/image_extractor/）
BASE_DIR = Path(__file__).resolve().parent

# services 目錄（bridge／daemon 所在——BASE_DIR 上一層）
SERVICES_DIR = BASE_DIR.parent

# 站台根（web／services 所在）
ROOT_DIR = SERVICES_DIR.parent

# 站台資料目錄（所有執行產物集中喺呢度）
DATA_DIR = ROOT_DIR / "data"


# 訊息根目錄（紙箱附件住 <box>/attachments/——抽取只准讀呢個樹之下）
MESSAGES_DIR = DATA_DIR / "messages"

# log（同其他套件同一個 logs 目錄）
LOG_DIR = DATA_DIR / "logs"
LOG_FILE = LOG_DIR / "image_extractor.log"
LOG_MAX_BYTES = 5 * 1024 * 1024

# OCR 前縮圖長邊上限（實測 4000px→2000px：9.19s→3.44s，結果相若）
OCR_MAX_SIDE_PX = 2000

# 單張圖硬上限（秒——超時即記 failed，唔阻下一張）
PER_IMAGE_TIMEOUT_SEC = 60

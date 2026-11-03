"""sd_trace.py —— 臨時追溯：邊個 spawn 點建立 %SystemDrive% 資料夾（查完即移除）

用法：spawn 前後各呼叫 probe("tag:before"/"tag:after", 工作目錄)
輸出：<project>/_tmp/sd_trace.log（一行一筆，append＋LOCK）
"""

from __future__ import annotations

import os
from datetime import datetime
from pathlib import Path

# 專案根（services 嘅上一層）
PROJECT_ROOT = Path(__file__).resolve().parent.parent

# 追溯輸出檔（_tmp 之下——唔污染 repo）
TRACE_FILE = PROJECT_ROOT / "_tmp" / "sd_trace.log"

# 被追蹤嘅資料夾名（Windows 未展開環境變數時嘅 literal 名）
TARGET_DIR_NAME = "%SystemDrive%"


def probe(tag: str, work_dir: str | Path | None = None) -> None:
    """記錄一點：時間／tag／pid／cwd／SystemDrive 有無／cwd 下 %SystemDrive% 是否存在。"""
    current_dir = Path(work_dir) if work_dir is not None else Path(os.getcwd())
    target_dir = current_dir / TARGET_DIR_NAME
    system_drive = os.environ.get("SystemDrive", "<missing>")
    created_text = "YES" if target_dir.is_dir() else "no"

    line = (
        datetime.now().strftime("%Y-%m-%d %H:%M:%S.%f")[:-3]
        + " | " + tag
        + " | pid=" + str(os.getpid())
        + " | cwd=" + str(current_dir)
        + " | SystemDrive=" + system_drive
        + " | folder=" + created_text
    )

    try:
        TRACE_FILE.parent.mkdir(parents=True, exist_ok=True)
        with open(TRACE_FILE, "a", encoding="utf-8") as trace_handle:
            trace_handle.write(line + "\n")
    except OSError:
        pass

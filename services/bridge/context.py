#!/usr/bin/env python3
"""
context.py

統一橋接層（Service Locator）——集中管理所有全局單例與上下文注入。

其他模組只需 `from .context import xxx`，無需關心底層初始化細節。
"""

import os
import sys
import time

from bootstrap.path import SERVICES_DIR

from . import BASE_DIR, DATA_DIR, ROOT_DIR, RUNTIME_DIR

# 固定工作目錄（唔信任 PHP／任何啟動者——一律以本檔位置為準）
os.chdir(SERVICES_DIR)

# 補 sys.path（-m 模式 sys.path[0]＝啟動時 cwd；換咗 cwd 仍要搵到 diagnostics 等 top-level 套件）
if str(SERVICES_DIR) not in sys.path:
    sys.path.insert(0, str(SERVICES_DIR))

# ===== 路徑（站台根 data/——核心路徑，唔靠 runtime.json） =====

# 心跳狀態目錄
PROCESS_DIR = DATA_DIR / "process_status"

# 本機資料庫（SQLite／goose 資料根／checkpoints——執行期資料）
LOCALDB_DIR = DATA_DIR / "localdb"

# 程序台帳（append-only JSONL）
PROCESS_FILE_LIST = PROCESS_DIR / "process_list.jsonl"

# 訊息目錄（站台根 data/messages）
MESSAGES_DIR = DATA_DIR / "messages"

# 待處理訊息（PHP 寫入 msg_conf）
MESSAGES_POLLING_DIR = MESSAGES_DIR / "polling"

# 已歸檔訊息（PHP 完成後搬入）
MESSAGES_ARCHIVE_DIR = MESSAGES_DIR / "archive"

# runtime.json（動態設定檔——固定喺站台 data/；唔再由 PHP 傳路徑）
RUNTIME_CONFIG_FILE = DATA_DIR / "runtime.json"

# BUILD 檔（站台根——發佈版本標記；release 檔名＝.build；build_file.py 讀）
BUILD_FILE = ROOT_DIR / ".build"

# 環境檔（站台根 .env——API keys）
ENV_FILE = ROOT_DIR / ".env"

# ===== 程式身份（心跳／台帳用——單一來源） =====

PROGRAM_NAME = "bridge"
PROGRAM_LABEL = "bridge.main"

# 有冇母親？（bridge：冇——PHP 經 launcher 拉起，launcher 開完即死，bridge 係根）
# 複製去 daemon 時改 True（母親＝bridge）
NEED_MOTHER = False

# ===== presence（頁面在線） =====

# 在線標記（最後一次有頁面連住——presence.py 讀寫）
PRESENCE_FILE = PROCESS_DIR / (PROGRAM_NAME + ".last_presence")

# SSE ping 間隔（秒——server_presence.py 每幾秒寫一次 ping 事件）
PRESENCE_PING_SEC = 3

# 同時連線上限（滿額 → 503；前端彈警告）
PRESENCE_MAX_CONNECTIONS = 32

# ===== 心跳／生命週期 =====

# 心跳間隔（秒——每輪寫狀態檔＋檢查母親／閒置）
HEARTBEAT_INTERVAL_SEC = 1.0

# 閒置自殺上限（秒——核心行為，唔放 runtime.json；＝3 個 ping 週期）
IDLE_EXIT_SEC = PRESENCE_PING_SEC * 3

# ===== agent daemon 喚醒 =====

# 拉起後等待 daemon 登記的上限（秒——收窄「未登記又被叫醒」的窗口）
DAEMON_WAKE_WAIT_SEC = 2.0


# ==========================================
# 統一獲取全局單例（Bridging Functions）
# ==========================================

def timer():
    """取得 timer_class 單例。"""
    from .utils.timer import timer_class
    return timer_class.get_instance()


def get_datetime() -> str:
    """取得秒精度時間字串（Y-m-d H:M:S）。"""
    return timer().get_datetime()


def get_timestamp() -> int:
    """取得 epoch 秒（int——同 PHP time()）。"""
    return int(time.time())


def strtotime(timestamp: int) -> str:
    """epoch 秒 → Y-m-d H:i:s 格式（PHP date() 同格式）。"""
    return timer().strtotime(timestamp)


def server():
    """取得 server_class 單例。"""
    from .server.server import server_class
    return server_class.get_instance()


# 對外匯出（供 `from .context import *`）——本檔 bridging 函數
__all__ = [
    "BASE_DIR",
    "DATA_DIR",
    "LOCALDB_DIR",
    "ENV_FILE",
    "HEARTBEAT_INTERVAL_SEC",
    "IDLE_EXIT_SEC",
    "MESSAGES_ARCHIVE_DIR",
    "MESSAGES_DIR",
    "MESSAGES_POLLING_DIR",
    "PRESENCE_MAX_CONNECTIONS",
    "PRESENCE_PING_SEC",
    "PROCESS_DIR",
    "PROCESS_FILE_LIST",
    "PROGRAM_LABEL",
    "NEED_MOTHER",
    "PROGRAM_NAME",
    "ROOT_DIR",
    "RUNTIME_DIR",
    "get_datetime",
    "get_timestamp",
    "server",
    "strtotime",
    "timer",
]

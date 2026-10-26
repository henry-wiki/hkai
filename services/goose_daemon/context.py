#!/usr/bin/env python3
"""
context.py

統一橋接層（Service Locator）——集中管理所有全局單例與上下文注入。

其他模組只需 `from .context import xxx`，無需關心底層初始化細節。
"""
import time

from . import BASE_DIR, DATA_DIR, ROOT_DIR, RUNTIME_DIR
from .runtime import identity

# ===== 程式身份（心跳／台帳用——常量源：runtime/identity.py） =====
PROGRAM_NAME = identity.DAEMON_PROGRAM_NAME
PROGRAM_LABEL = identity.DAEMON_PROGRAM_LABEL

# 心跳間隔（秒——heartbeat 執行緒每拍一次；主迴圈節奏另見 daemon_manager.MAIN_LOOP_INTERVAL_SEC）
HEARTBEAT_INTERVAL_SEC = 1.0

# 暖 worker 閒置自殺（秒——收唔到工單即自己退出；daemon 下一輪清登錄）
WARM_WORKER_IDLE_EXIT_SEC = 30

# 閒置自殺門檻（秒——同 bridge 同名同結構；判斷條件唔同：呢邊＝名冊冇工人）
IDLE_EXIT_SEC = 30

# 有冇母親？（daemon：有——母親＝bridge；bridge 死 → 自殺）
NEED_MOTHER = True

# 心跳狀態目錄（站台根 data/process_status）
PROCESS_DIR = DATA_DIR / "process_status"

# 本機資料庫（SQLite／goose 資料根／checkpoints——執行期資料）
LOCALDB_DIR = DATA_DIR / "localdb"

# 程序台帳（append-only JSONL——核心路徑，唔靠 runtime.json）
PROCESS_FILE_LIST = PROCESS_DIR / "process_list.jsonl"

# 訊息目錄（站台根 data/messages——PHP 寫入；daemon 讀取）
MESSAGES_DIR = DATA_DIR / "messages"

# 待處理訊息（PHP 寫入 msg_conf）
MESSAGES_POLLING_DIR = MESSAGES_DIR / "polling"

# 已歸檔訊息（PHP 完成後搬入）
MESSAGES_ARCHIVE_DIR = MESSAGES_DIR / "archive"

# BUILD 檔（站台根 .build——發佈版本標記；同 bridge 一致；build_file.py 讀）
BUILD_FILE = ROOT_DIR / ".build"

# 環境檔（站台根 .env——sc_env 讀取＋注入）
ENV_FILE = ROOT_DIR / ".env"


# runtime.json（動態設定檔——固定喺站台 data/；唔再由外部傳路徑）

RUNTIME_CONFIG_FILE = DATA_DIR / "runtime.json"

# ==========================================
# 統一獲取全局單例（Bridging Functions）
#
# 各函數刻意「函數內延遲 import」——運行時單向（context 先載入、使用時才拉子模組）；
# basedpyright 靜態掃描會標成 import cycle，故本檔停用該規則（檔案級）。
# pyright: reportImportCycles=false
#
# 各函數刻意「函數內延遲 import」——運行時單向（context 先載入、使用時才拉子模組），
# 唔存在實際循環；basedpyright 靜態掃描仍會視為 import cycle，故逐行標註忽略。
# ==========================================
def timer():
    """取得 timer_class 單例。"""
    from .utils.timer import timer_class
    return timer_class.get_instance()

def daemon():
    """取得 daemon_manager_class 單例。"""
    from .daemon.daemon_manager import (
        daemon_manager_class,  # 延遲——避免循環 import
    )
    return daemon_manager_class.get_instance()

def get_timestamp() -> int:
    """取得 epoch 秒（int——同 PHP time()）。"""
    return int(time.time())


def strtotime(timestamp: int) -> str:
    """epoch 秒 → Y-m-d H:i:s 格式（PHP date() 同格式）。"""
    return timer().strtotime(timestamp)


def get_datetime() -> str:
    """取得秒精度時間字串（Y-m-d H:M:S）。"""
    return timer().get_datetime()

def conversation_sync():
    """取得 conversation_sync_class 單例。"""
    # 延遲——避免循環 import
    from .worker.agent.conversation_sync import (
        conversation_sync_class,
    )
    return conversation_sync_class.get_instance()

def acp_client():
    """取得 acp_client 單例。"""
    from .worker.agent.acp_client import acp_client  # 延遲——避免循環 import
    return acp_client.get_instance()

def entry():
    """取得 entry_class 單例。"""
    from .worker.agent.entry import (
        entry_class,  # 延遲——避免循環 import
    )
    return entry_class.get_instance()

def job_executor():
    """取得 job_executor_class 單例。"""
    from .worker.job_executor import (
        job_executor_class,  # 延遲——避免循環 import
    )
    return job_executor_class.get_instance()

def message_log():
    """取得 message_log_class 單例。"""
    from .worker.message_log import message_log_class  # 延遲——避免循環 import
    return message_log_class.get_instance()

def job_stdout():
    """取得 job_stdout_class 單例。"""
    from .worker.job_stdout import job_stdout_class  # 延遲——避免循環 import
    return job_stdout_class.get_instance()

def message_claim():
    """取得 message_claim_class 單例。"""
    from .msg.message_claim import message_claim_class  # 延遲——避免循環 import
    return message_claim_class.get_instance()

def message_store():
    """取得 message_store_class 單例。"""
    from .msg.message_store import message_store_class  # 延遲——避免循環 import
    return message_store_class.get_instance()

def chat_db():
    """取得 chat_db_class 單例（我們 DB 唯讀）。"""
    from .db.db_hkai import chat_db_class  # 延遲——避免循環 import
    return chat_db_class.get_instance()


def goose_db(project_path: str):
    """建立 goose_db_class（每專案一個 sessions DB——按 project_path）。"""
    from .db.db_goose import goose_db_class  # 延遲——避免循環 import
    return goose_db_class(project_path)


def message_status():
    """取得 message_status_class 單例。"""
    from .msg.message_status import message_status_class  # 延遲——避免循環 import
    return message_status_class.get_instance()

# 對外匯出（供 `from .context import *`）——本檔 bridging 函數
__all__ = [
    "BASE_DIR",
    "DATA_DIR",
    "ENV_FILE",
    "HEARTBEAT_INTERVAL_SEC",
    "IDLE_EXIT_SEC",
    "LOCALDB_DIR",
    "MESSAGES_ARCHIVE_DIR",
    "MESSAGES_DIR",
    "MESSAGES_POLLING_DIR",
    "NEED_MOTHER",
    "PROCESS_DIR",
    "PROCESS_FILE_LIST",
    "PROGRAM_LABEL",
    "PROGRAM_NAME",
    "ROOT_DIR",
    "RUNTIME_CONFIG_FILE",
    "RUNTIME_DIR",
    "WARM_WORKER_IDLE_EXIT_SEC",
    "acp_client",
    "chat_db",
    "daemon",
    "goose_db",
    "get_datetime",
    "get_timestamp",
    "entry",
    "conversation_sync",
    "job_executor",
    "job_stdout",
    "message_claim",
    "message_log",
    "message_status",
    "message_store",
    "strtotime",
    "timer",
]

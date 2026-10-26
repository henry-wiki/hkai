#!/usr/bin/env python3
"""
context.py

統一橋接層（Service Locator）——集中管理所有全局單例與上下文注入。

其他模組只需 `from .context import xxx`，無需關心底層初始化細節。
"""
from . import BASE_DIR, DATA_DIR, ROOT_DIR, RUNTIME_DIR

# ===== 程式身份（心跳／台帳用——單一來源） =====
PROGRAM_NAME = "openhands_api_daemon"
PROGRAM_LABEL = "openhands_api_daemon.main"

# 有冇母親？（daemon：有——母親＝bridge；bridge 死 → 自殺）
NEED_MOTHER = True

# 心跳狀態目錄（站台根 data/process_status）
PROCESS_DIR = DATA_DIR / "process_status"

# runtime.json（動態設定檔——固定喺站台 data/；唔再由外部傳路徑）
RUNTIME_CONFIG_FILE = DATA_DIR / "runtime.json"

# 環境檔（站台根 .env——API keys；api_key_loader 讀）
ENV_FILE = ROOT_DIR / ".env"

# ==========================================
# 統一獲取全局單例（Bridging Functions）
# ==========================================
def timer():
    """取得 timer_class 單例。"""
    from .utils.timer import timer_class
    return timer_class.get_instance()

def daemon():
    """取得 daemon_manager_class 單例。"""
    from .daemon.daemon_manager import daemon_manager_class  # 延遲——避免循環 import
    return daemon_manager_class.get_instance()

def get_datetime() -> str:
    """取得秒精度時間字串（Y-m-d H:M:S）。"""
    return timer().get_datetime()

def process_helper():
    """取得 process_helper_class 單例。"""
    from .utils.process_helper import process_helper_class  # 延遲——避免循環 import
    return process_helper_class.get_instance()

def child_memory_guard():
    """取得 child_memory_guard_class 單例。"""
    from .daemon.child_memory_guard import child_memory_guard_class  # 延遲——避免循環 import
    return child_memory_guard_class.get_instance()

def openhands_api_entry():
    """取得 openhands_api_entry_class 單例。"""
    from .agent.openhands_api_entry import openhands_api_entry_class  # 延遲——避免循環 import
    return openhands_api_entry_class.get_instance()

def msg_processor():
    """取得 msg_processor_class 單例。"""
    from .msg.msg_processor import msg_processor_class  # 延遲——避免循環 import
    return msg_processor_class.get_instance()

def msg_log():
    """取得 msg_log_class 單例。"""
    from .msg.msg_log import msg_log_class  # 延遲——避免循環 import
    return msg_log_class.get_instance()

def msg_stdout():
    """取得 msg_stdout_class 單例。"""
    from .msg.msg_stdout import msg_stdout_class  # 延遲——避免循環 import
    return msg_stdout_class.get_instance()

def msg_claim():
    """取得 msg_claim_class 單例。"""
    from .msg.msg_claim import msg_claim_class  # 延遲——避免循環 import
    return msg_claim_class.get_instance()

def msg_main():
    """取得 msg_main_class 單例。"""
    from .msg.msg_main import msg_main_class  # 延遲——避免循環 import
    return msg_main_class.get_instance()

def msg_state_machine():
    """取得 msg_state_machine_class 單例。"""
    from .msg.msg_state_machine import msg_state_machine_class  # 延遲——避免循環 import
    return msg_state_machine_class.get_instance()

# 對外匯出（供 `from .context import *`）——本檔 bridging 函數
__all__ = [
    "BASE_DIR",
    "DATA_DIR",
    "ENV_FILE",
    "NEED_MOTHER",
    "PROCESS_DIR",
    "PROGRAM_LABEL",
    "PROGRAM_NAME",
    "ROOT_DIR",
    "RUNTIME_CONFIG_FILE",
    "RUNTIME_DIR",
    "child_memory_guard",
    "daemon",
    "get_datetime",
    "openhands_api_entry",
    "msg_claim",
    "msg_log",
    "msg_main",
    "msg_processor",
    "msg_stdout",
    "msg_state_machine",
    "process_helper",
    "timer",
]

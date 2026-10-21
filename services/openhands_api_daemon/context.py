#!/usr/bin/env python3
"""
context.py

統一橋接層（Service Locator）——集中管理所有全局單例與上下文注入。

其他模組只需 `from .context import xxx`，無需關心底層初始化細節。
"""
from . import BASE_DIR, RUNTIME_DIR

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

def exit_with_children(exit_code: int = 1) -> None:
    """自殺連帶自己全部子孫（只落子樹——唔掂母程序）。

    terminate → 逾時 → kill；最後 os._exit（唔行 cleanup）。
    """
    import os
    import psutil

    # 自己嘅全部子孫（遞歸——只向下）
    try:
        child_processes = psutil.Process(os.getpid()).children(recursive=True)
    except psutil.Error:
        child_processes = []

    # 先客氣（terminate）
    for child_process in child_processes:
        try:
            child_process.terminate()
        except psutil.Error:
            pass

    # 逾時未死 → 硬殺
    _gone_processes, alive_processes = psutil.wait_procs(child_processes, timeout=5)
    for child_process in alive_processes:
        try:
            child_process.kill()
        except psutil.Error:
            pass

    # 自己收工（立即——唔行 atexit）
    os._exit(exit_code)

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
    from .engine.openhands_api_entry import openhands_api_entry_class  # 延遲——避免循環 import
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
    "RUNTIME_DIR",
    "child_memory_guard",
    "daemon",
    "exit_with_children",
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

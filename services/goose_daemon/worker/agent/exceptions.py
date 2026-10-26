#!/usr/bin/env python3
"""
exceptions.py

agent嚴重錯誤收尾（單一職責：寫 error log／工作狀態，回 False 通知上層解僱工人）。

上游：entry.execute_job（預檢失敗／歷史同步失敗／spawn／串流失敗）
下游：worker/message_events（事件詞彙）、context（message_log／message_status）、runtime/sc_logger

規則：只有「整張工單失敗」才入呢個模組；非致命（例如 hints 內容無變）唔屬於呢層。
"""

from ...context import message_log, message_status
from ...runtime import sc_logger
from ..message_events import EVENT_FAILURE_RECORDED
from .preflight import PreflightError


def record_job_failure(exc: Exception, chat_id: str) -> bool:
    """記錄工單例外失敗（error log＋狀態）——回 False（工人隨後退出）。"""
    # 例外訊息寫入 message_log（ERROR 事件——短句進 message_log、完整內容進 error_log）
    _ = message_log().append_error_log(
        EVENT_FAILURE_RECORDED,
        type(exc).__name__,
        {
            "exception_type": type(exc).__name__,
            "exception_message": str(exc),
        },
        detail=str(exc),
    )
    sc_logger.log("error chat_id=" + str(chat_id) + ": " + str(exc))   # sc_logger.log 內部盲捕——已安全
    _ = message_status().apply(EVENT_FAILURE_RECORDED)
    return False


def record_preflight_failure(preflight_error: PreflightError) -> bool:
    """記錄預檢失敗（model／API key／執行檔／專案路徑）——回 False（工人隨後退出）。"""
    _ = message_log().append_error_log(
        EVENT_FAILURE_RECORDED,
        preflight_error.message,
        preflight_error.detail,
    )
    _ = message_status().apply(EVENT_FAILURE_RECORDED)
    return False

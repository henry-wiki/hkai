#!/usr/bin/env python3
"""
exceptions.py

agent嚴重錯誤收尾（單一職責：寫 error log／工作狀態，回 False 通知上層解僱工人）。

上游：entry.execute_job（預檢失敗／歷史同步失敗／spawn／串流失敗）
下游：worker/message_events（事件詞彙）、context（message_log／message_status）、bootstrap/sc_logger

規則：只有「整張工單失敗」才入呢個模組；非致命（例如 hints 內容無變）唔屬於呢層。
"""

from collections.abc import Callable
from typing import cast

from ...context import message_log, message_status, message_store
from ... import context as daemon_context
from bootstrap import sc_logger
from .. import error_text as _error_text
from ..message_events import EVENT_FAILURE_RECORDED
from .preflight import PreflightError

# 型別：error_text 未標註——cast 取回已標註函式（唔改行為）
_classify_error = cast("Callable[[str], tuple[str, str, bool]]", _error_text.classify_error)
_append_agent_error_log = cast("Callable[[str, str, str, str, str, str], None]", _error_text.append_agent_error_log)


def record_job_failure(exc: Exception, chat_id: str) -> bool:
    """記錄工單例外失敗（分類 → 寫回應 result＋error log＋狀態）——回 False（工人隨後退出）。"""
    raw_text = str(exc)

    # 分類（同一套錯誤標準——error_rules.json；唔指定供應商名）
    error_code, display_text, matched = _classify_error(raw_text)

    # 判定：認唔出類型 → 原文照顯示（唔假裝成功）
    if matched is False:
        display_text = raw_text.strip()

    # 例外訊息寫入 message_log（ERROR 事件——短句進 message_log、完整內容進 error_log）
    _ = message_log().append_error_log(
        EVENT_FAILURE_RECORDED,
        type(exc).__name__,
        {
            "exception_type": type(exc).__name__,
            "exception_message": raw_text,
        },
        detail=raw_text,
    )

    # 錯誤顯示文字寫入回應 result（錯誤＝回應正文；reload 仍見）
    _ = message_log().append_response_result(0, display_text)

    # 完整原文寫 agent-errors.log（除錯用）
    _append_agent_error_log(
        str(daemon_context.DATA_DIR) + "/logs/agent-errors.log",
        message_store().get_message_id(),
        "",
        message_store().get_model_name(),
        raw_text,
        error_code,
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

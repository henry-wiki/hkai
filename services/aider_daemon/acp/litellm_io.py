#!/usr/bin/env python3
"""
litellm_io.py

🧩 可拆模組——tool 訊息分流 io（復刻舊 chat_daemon/adapter/litellm_io.py）。

職責單一：aider 的 tool 訊息（提示／錯誤／警告／統計）導向 msg_log 事件；
stdout 只收原樣輸出（唔被 tool 行污染）。

背景：
    aider 內部重試時會 print "Retrying in X seconds..."（base_coder.py:1486），
    原版經 console → 原始輸出通道；本 class 在產生源攔截分流——
    AI 回答走 console.print 通道（不經 tool_*），不受影響。

載入時機：
    本模組頂層 import aider——只可由 core._build_io／permission 於建 Coder 時
    延遲 import（與 aider 同時機；唔好喺 package 頂層 import）。

移除方法：直接刪咗本檔——core._build_io／permission 嘅 try-import 回落原版
InputOutput（tool 訊息變回原始輸出）；其餘照跑（零代碼改動）。
"""

from aider.io import InputOutput

from ..msg.msg_events import (
    EVENT_TOOL_FAILED,
    EVENT_TOOL_INVOKED,
    EVENT_TOOL_STATS_RECORDED,
    EVENT_TOOL_WARNING_RECORDED,
)


class litellm_io_class(InputOutput):
    """
    tool 訊息分流（繼承 aider InputOutput——只覆寫 tool 三兄弟，其餘行為原樣）。
    """

    # 覆寫 tool_output（簽名對齊 base io.py:995；log_only / bold 刻意不用——分流後一律寫 msg_log）
    def tool_output(self, *messages, log_only=False, bold=False):
        # 空內容不寫（避免噪音行）；tokens/cost 統計行與一般提示行分名（TOOL_STATS / TOOL_INVOKED）
        self._write_tool_log(messages, "info")

    # 覆寫 tool_error（簽名對齊 base io.py:988；strip 刻意不用）
    def tool_error(self, message="", strip=True):
        self._write_tool_log([message], "error")

    # 覆寫 tool_warning（簽名對齊 base io.py:992；strip 刻意不用）
    def tool_warning(self, message="", strip=True):
        self._write_tool_log([message], "warn")

    def _write_tool_log(self, messages: tuple | list, log_kind: str) -> None:
        """多訊息合併單行 → 寫入 msg_log（原版 tool_output 支援多參數；寫入異常一律吞——通道保真）。"""
        log_text = " ".join(str(message_content) for message_content in messages)
        log_text = log_text.strip()

        # 空內容不寫（aider 會以空字串呼叫 tool_output——純噪音）
        if log_text == "":
            return

        # 依內容分流事件（統計行 vs 一般提示）＋層級正確化
        try:
            if log_kind == "error":
                _msg_log().append_error_log(EVENT_TOOL_FAILED, log_text)
            elif log_kind == "warn":
                _msg_log().append_warn_log(EVENT_TOOL_WARNING_RECORDED, log_text)
            elif log_text.startswith("Tokens:"):
                _msg_log().append_info_log(EVENT_TOOL_STATS_RECORDED, log_text)
            else:
                _msg_log().append_info_log(EVENT_TOOL_INVOKED, log_text)
        except Exception:  # noqa: BLE001, S110
            pass


# ==========================================
# 延遲取用（非 daemon 拉起時——本模組零載入負擔）
# ==========================================
def _msg_log():
    """msg_log 單例。"""
    from ..context import msg_log

    return msg_log()

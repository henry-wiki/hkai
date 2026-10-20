#!/usr/bin/env python3
"""
permission.py

🧩 可拆模組——等確認（aider 側唯一入口：InputOutput.confirm_ask）。

職責：aider 每次想確認（改檔／指令等）→ 唔走終端——改交 ACP 卡
（core.confirm_via_client → request_permission）→ 等 client 答 → 回 bool。

移除方法：直接刪咗本檔——core.py try-import 唔到 → 自動回落自動模式
（AIDER_ACP_AUTO_YES——默認拒絕）；其餘代碼零改動。
"""

import sys
from io import StringIO

# 🧩 底類：litellm_io（tool 訊息分流——缺席＝回落原版 InputOutput）
try:
    from .litellm_io import litellm_io_class as _io_base_class
except ImportError:
    from aider.io import InputOutput as _io_base_class


class permission_io_class(_io_base_class):
    """InputOutput 子類——tool 分流（底類 litellm_io）＋confirm_ask 轉 ACP 卡（其餘照 base 走）。"""

    def __init__(self, agent_ref, session_id, **input_output_kwargs) -> None:
        super().__init__(**input_output_kwargs)
        self._agent_ref = agent_ref
        self._session_id = session_id

    # aider 確認點（改檔／指令等）→ ACP 卡（client 出卡、等用戶撳）→ 返准／唔准
    def confirm_ask(
        self,
        question,
        default="y",
        subject=None,
        explicit_yes_required=False,
        group=None,
        allow_never=False,
    ):
        return bool(self._agent_ref.confirm_via_client(self._session_id, question, subject))


def build_io(agent_ref, session_id):
    """建 permission io（同 core 自動模式同一批底參數——只差「確認去邊」）。"""
    return permission_io_class(
        agent_ref=agent_ref,
        session_id=session_id,
        yes=False,
        pretty=False,
        encoding="utf-8",
        input=StringIO(""),
        output=sys.stderr,
        fancy_input=False,
    )

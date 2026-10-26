#!/usr/bin/env python3
# pyright: reportAny=false, reportExplicitAny=false
"""
acp_session_client.py

Client 介面實作（SDK 結構式 Client——只實作本側需要嘅兩支）。

上游：acp_client.execute_session（connect_to_agent 時傳入）
下游：on_update／on_permission_request（session.build_callbacks 提供）

session_update     → 交 on_update（正規化；重播閘開前內容類交 replay）
request_permission → 交 on_permission_request（async——等使用者決定後回選項 id）
其餘方法缺席：SDK router 自動答 method_not_found（fs）／optional 默認（terminal／elicitation）。
"""

import json
from collections.abc import Awaitable, Callable
from typing import Any, cast

from acp.schema import (
    AgentMessageChunk,
    AgentThoughtChunk,
    AllowedOutcome,
    RequestPermissionResponse,
    ToolCallProgress,
    ToolCallStart,
    UsageUpdate,
)

# 權限選項 id（goose 選項中本側只用兩態——允許一次／拒絕一次）
PERMISSION_OPTION_ALLOW = "allow_once"
PERMISSION_OPTION_REJECT = "reject_once"


class acp_session_client:
    """
    Client 介面實作（SDK 結構式——只實作本側需要嘅兩支）：
        session_update     → 交 on_update（正規化後——見檔頭對照表；
                              重播閘開前〔prompt 未出〕內容類以「replay」kind 交、其餘只數
                              ——見 release_replay_guard）
        request_permission → 交 on_permission_request（async——等使用者決定後回選項 id）
    其餘方法缺席：SDK router 自動以 method_not_found（fs 讀寫）／optional 默認值（terminal／elicitation）答覆。
    """

    _on_update: Callable[[str, dict[str, object]], None]
    _on_permission_request: Callable[[object], Awaitable[str]]
    _is_replay_guard_active: bool
    _dropped_update_count: int

    def __init__(self, on_update: Callable[[str, dict[str, object]], None], on_permission_request: Callable[[object], Awaitable[str]]) -> None:
        self._on_update = on_update
        self._on_permission_request = on_permission_request

        # 重播閘（初始＝閘前狀態；prompt 一出才開——見 release_replay_guard）
        self._is_replay_guard_active = True
        self._dropped_update_count = 0

    async def session_update(self, session_id: str, update: object, **kwargs: object) -> None:  # pyright: ignore[reportUnusedParameter]
        """session 事件（notification——重播閘開後逐個交呼叫端；閘前內容類另交 replay 頻道）。"""
        update_kind, update_payload = _normalize_update(update)

        # 判定：重播閘未開（prompt 未出——load 重播期）→ 唔當本輪回應
        if self._is_replay_guard_active is True:
            self._dropped_update_count = self._dropped_update_count + 1

            # 判定：內容類（text／thought）→ 交 replay 頻道（呼叫端獨立歸檔；唔入回應檔）
            if update_kind in ("text", "thought"):
                self._on_update("replay", update_payload)

            return

        self._on_update(update_kind, update_payload)

    def release_replay_guard(self) -> int:
        """開重播閘（prompt 一出＝本輪回應開始）；回傳閘前丟棄數（供記行）。"""
        self._is_replay_guard_active = False
        return self._dropped_update_count

    async def request_permission(self, session_id: str, tool_call: object, options: object, **kwargs: object) -> RequestPermissionResponse:  # pyright: ignore[reportUnusedParameter]
        """權限請求（goose defer 出嚟）——交呼叫端（async）等決定 → 回協定選項。"""
        option_id = await self._on_permission_request(tool_call)

        # 判定：選項 id 缺漏 → 保守拒（拒＝安全側——同逾時語義一致）
        if option_id == "":
            option_id = PERMISSION_OPTION_REJECT

        return RequestPermissionResponse(outcome=AllowedOutcome(outcome="selected", option_id=option_id))


# ----------------------------------------------------------
# 事件正規化（SDK 類別 → 中立 kind——見檔頭對照表）
# ----------------------------------------------------------

def _normalize_update(update: Any) -> tuple[str, dict[str, object]]:
    """SDK update 類別 → (kind, payload)。"""
    # 思考段（reasoning chunk）
    if isinstance(update, AgentThoughtChunk):
        return "thought", {"text": _extract_chunk_text(update), "message_id": _extract_message_id(update)}

    # 主回答段（message chunk）
    if isinstance(update, AgentMessageChunk):
        return "text", {"text": _extract_chunk_text(update), "message_id": _extract_message_id(update)}

    # 工具活動（開始／進度）
    if isinstance(update, (ToolCallStart, ToolCallProgress)):
        return "tool", {
            "tool_call_id": str(getattr(update, "tool_call_id", "") or ""),
            "title":        str(getattr(update, "title", "") or ""),
            "message_id":   _extract_message_id(update),
        }

    # 用量更新（goose 報 context 用量／成本）
    if isinstance(update, UsageUpdate):
        return "usage", _extract_usage(update)

    # 其餘（plan／commands／mode 變更等）——原樣轉存
    return "other", {"update_dump": dump_protocol_message(update.model_dump(by_alias=True))}


def _extract_chunk_text(update: Any) -> str:
    """抽 chunk 文字（content 為文字塊——非文字／缺＝空字串）。"""
    content_block = getattr(update, "content", None)

    # 判定：無內容 → 空字串
    if content_block is None:
        return ""

    return str(getattr(content_block, "text", "") or "")


def _extract_message_id(update: Any) -> str:
    """抽訊息 id（SDK message_id 欄；舊格式 fallback 去 _meta.messageId——段落 index 判定用；缺＝空字串）。"""
    message_id = str(getattr(update, "message_id", "") or "")

    # 判定：SDK 欄有值 → 回傳
    if message_id != "":
        return message_id

    update_meta = getattr(update, "field_meta", None)

    # 判定：無 _meta → 空字串
    if not isinstance(update_meta, dict):
        return ""

    typed_meta = cast("dict[str, object]", update_meta)

    return str(typed_meta.get("messageId", "") or "")


def _extract_usage(update: Any) -> dict[str, Any]:
    """抽用量（used／size／cost——成本只取金額；agent報 USD）。"""
    usage_payload = {
        "used":     getattr(update, "used", 0),
        "size":     getattr(update, "size", 0),
        "cost_usd": 0,
    }

    cost = getattr(update, "cost", None)

    # 判定：有成本物件 → 取金額
    if cost is not None:
        usage_payload["cost_usd"] = getattr(cost, "amount", 0)

    return usage_payload


def dump_protocol_message(protocol_message: Any) -> str:
    """序列化單條協定訊息（失敗＝後備 repr——紀錄唔准斷）。"""
    try:
        return json.dumps(protocol_message, ensure_ascii=False, default=str)
    except Exception:  # noqa: BLE001
        return repr(protocol_message)

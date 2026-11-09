#!/usr/bin/env python3
"""
server_daemon.py — daemon 通知端點（依 daemon_engine 喚醒現行agent）。

職責：
- add_message()：POST /daemon/add_message/（PHP 呼叫——通知有新 msg 落地；bridge 依 daemon_engine 決定喚醒邊個 daemon）
"""

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false
# （payload 係動態 JSON——Unknown/Any 警告一律關，其餘照檢）

from typing import Any

from fastapi import APIRouter

from ..agent_daemon import daemon_wake
from bootstrap import sc_logger

router = APIRouter()


@router.post("/daemon/add_message/")
def add_message(payload: dict[str, Any]) -> dict[str, Any]:
    """PHP 通知有新 msg 落地 → 確保現行agent daemon 運行（喚醒與否由 bridge 決定）。"""
    chat_id = _to_int(payload.get("chat_id"))
    message_id = _to_int(payload.get("message_id"))
    agent      = str(payload.get("agent") or "")

    # 判定：chat_id 無效（0／非數字）→ 記 log 並拒絕
    if chat_id < 1:
        sc_logger.log("add_message invalid: chat_id=" + str(payload.get("chat_id")) + " message_id=" + str(payload.get("message_id")))
        return {"ok": False, "error": "invalid chat_id", "chat_id": chat_id, "message_id": message_id}

    # 判定：message_id 無效（0／非數字）→ 記 log 並拒絕
    if message_id < 1:
        sc_logger.log("add_message invalid: chat_id=" + str(payload.get("chat_id")) + " message_id=" + str(payload.get("message_id")))
        return {"ok": False, "error": "invalid message_id", "chat_id": chat_id, "message_id": message_id}

    wake_result = daemon_wake.wake_daemon(agent)

    # 判定：喚醒失敗（設定缺失／啟動逾時）→ 記 log，錯誤原樣交返 PHP
    if wake_result["ok"] is False:
        sc_logger.log(f"add_message wake failed: {wake_result['error']} chat_id={chat_id} message_id={message_id}")
        return {
            "ok": False,
            "error": wake_result["error"],
            "chat_id": chat_id,
            "message_id": message_id,
        }

    return {
        "ok": True,
        "chat_id": chat_id,
        "message_id": message_id,
    }


def _to_int(payload_value: Any) -> int:
    """轉整數（非數字／None → 0）。"""
    try:
        return int(payload_value)
    except (TypeError, ValueError):
        return 0

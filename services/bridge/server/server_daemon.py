#!/usr/bin/env python3
"""
server_daemon.py — daemon 通知端點（依 daemon_engine 喚醒現行引擎）。

職責：
- add_message()：POST /daemon/add_message/（PHP 呼叫——通知有新 msg 落地；bridge 依 daemon_engine 決定喚醒邊個 daemon）
"""

from fastapi import APIRouter, Request

from ..agent_daemon import daemon_wake
from ..runtime import sc_logger

router = APIRouter()


@router.post("/daemon/add_message/")
def add_message(request: Request, payload: dict) -> dict:
    """PHP 通知有新 msg 落地 → 確保現行引擎 daemon 運行（喚醒與否由 bridge 決定）。"""
    chat_id = _to_int(payload.get("chat_id"))
    message_id = _to_int(payload.get("message_id"))

    # 判定：chat_id 無效（0／非數字）→ 記 log 並拒絕
    if chat_id < 1:
        sc_logger.log("add_message invalid: chat_id=" + str(payload.get("chat_id")) + " message_id=" + str(payload.get("message_id")))
        return {"ok": False, "error": "invalid chat_id", "chat_id": chat_id, "message_id": message_id}

    # 判定：message_id 無效（0／非數字）→ 記 log 並拒絕
    if message_id < 1:
        sc_logger.log("add_message invalid: chat_id=" + str(payload.get("chat_id")) + " message_id=" + str(payload.get("message_id")))
        return {"ok": False, "error": "invalid message_id", "chat_id": chat_id, "message_id": message_id}

    wake_result = daemon_wake.wake_daemon()

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


def _to_int(payload_value) -> int:
    """轉整數（非數字／None → 0）。"""
    try:
        return int(payload_value)
    except (TypeError, ValueError):
        return 0

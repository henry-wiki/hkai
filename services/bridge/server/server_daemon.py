#!/usr/bin/env python3
"""
server_daemon.py — daemon 通知端點（依 daemon_engine 喚醒現行引擎）。

職責：
- add_message()：POST /daemon/add_message/（PHP 呼叫——通知有新 msg 落地；bridge 依 daemon_engine 決定喚醒邊個 daemon）
"""

from fastapi import APIRouter, Request

from ..daemon.daemon_wake import wake_daemon

router = APIRouter()


@router.post("/daemon/add_message/")
def add_message(request: Request, payload: dict) -> dict:
    """PHP 通知有新 msg 落地 → 確保現行引擎 daemon 運行（喚醒與否由 bridge 決定）。"""
    config = request.app.state.config
    wake_result = wake_daemon(config)

    return {
        "ok": True,
        "chat_id": payload.get("chat_id", 0),
        "message_id": payload.get("message_id", 0),
        "started": wake_result["started"],
        "alive": wake_result["alive"],
    }

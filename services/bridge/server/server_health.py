#!/usr/bin/env python3
"""
server_health.py — 存活檢查端點。

職責：
- build_router()：建立路由器（health 路徑＝固定契約 "/health"——同 PHP 檢查端硬編碼同一值）
- health()：GET /health 端點
- daemon_alive()：GET /daemon_alive 端點
"""

import os

from fastapi import APIRouter, Request

from ..daemon.daemon_registry import is_daemon_alive


def build_router() -> APIRouter:
    """建立健康檢查路由器（health 路徑＝固定契約 "/health"——與 PHP 檢查端同一值）。"""
    router = APIRouter()
    router.add_api_route("/health", health)
    router.add_api_route("/daemon_alive", daemon_alive)
    return router


def health(request: Request) -> dict:
    """回傳伺服器存活狀態＋身份（storage_dir 判別實例；pid 供核對）。"""
    config = getattr(request.app.state, "config", {}) or {}

    health_data = {}
    health_data["status"] = "ok"
    health_data["storage_dir"] = str(config.get("storage_dir", ""))
    health_data["pid"] = os.getpid()
    return health_data


def daemon_alive() -> dict:
    """daemon 存活檢查（特殊命令——PHP bridge 呼叫；本機原生查 pid）。"""
    # TODO: 從 app.state.config 取得 config
    return {"daemon_alive": False}  # TODO: is_daemon_alive(config, read_daemon_engine(config))

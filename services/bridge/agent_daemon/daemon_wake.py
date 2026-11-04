#!/usr/bin/env python3
"""
daemon_wake.py — 現行agent daemon 拉起（wake）。

職責：
- wake_daemon()：確保現行 agent daemon 運行——未運行則以 detached 方式拉起。
  （供 server_daemon 的 add_message 呼叫；不對外開路由——外部不得直呼）

說明：
- 現行 agent daemon＝runtime.json 唯一來源（daemon_engine；冇 fallback）
- 啟動命令：python -m {agent_daemon}.main（cwd = services；daemon 自己讀 data/runtime.json）
- runtime 路徑＝context.RUNTIME_CONFIG_FILE（data/runtime.json，食死）
- Python 執行檔直接讀 .env（python_executable——唔經 runtime.json）
"""

import subprocess
import sys
import time
from pathlib import Path
from typing import cast

from .. import context
from ..runtime import sc_config, sc_env, sc_logger
from .daemon_registry import is_daemon_alive


def _resolve_agent_daemon(agent: str) -> str:
    """agent 名 → daemon 包名（manifest agents 對映表；舊 runtime 值＝daemon 名直接沿用）。"""
    agents_config = sc_config.get_config("agents")

    # 判定：對映表係 dict → 查 agent 條目
    if isinstance(agents_config, dict):
        agents_config_map = cast("dict[str, object]", agents_config)
        agent_item = agents_config_map.get(agent, {})
        # 判定：條目係 dict → 取 daemon 名
        if isinstance(agent_item, dict):
            agent_item_map = cast("dict[str, object]", agent_item)
            agent_daemon = str(agent_item_map.get("daemon") or "")
            # 判定：有 daemon 名 → 回傳
            if agent_daemon != "":
                return agent_daemon

    # 舊 runtime.json 兼容（值本身係 daemon 名）→ 直接沿用
    return str(sc_config.get_config("daemon_engine") or "")


def wake_daemon(agent: str = "") -> dict[str, object]:
    """確保指定 agent 對應 daemon 運行；未運行則拉起（detached——不隨 bridge 生命週期）。

    agent：該訊息落地時蓋章嘅 agent id（payload 帶）；空＝fallback runtime.json 現行值。
    """
    # 判定：payload 無 agent → 用 runtime 現行值（舊呼叫兼容）
    if agent == "":
        agent = str(sc_config.get_config("agent") or "")

    agent_daemon = _resolve_agent_daemon(agent)

    # 判定：查唔到 daemon（未設 agent／對映表缺）→ 記 log 並拒絕（唔代佢揀）
    if agent_daemon == "":
        sc_logger.log("wake_daemon failed: agent missing in runtime.json")
        return {"ok": False, "error": "missing agent"}

    # 判定：已在運行 → 不重複拉起
    if is_daemon_alive(agent_daemon):
        return {"ok": True}

    python_executable = str(sc_env.get_env("python_executable") or "")

    # 判定：前置缺失（無法拉起）→ 回報失敗
    if python_executable == "":
        return {"ok": False, "error": "missing python_executable"}

    # 套件根目錄（bridge/agent_daemon/daemon_wake.py → services）
    services_dir = Path(__file__).resolve().parents[2]

    command = [python_executable, "-u", "-m", agent_daemon + ".main"]

    # 判定：Windows → 無視窗＋新程序群組；POSIX → 新 session（皆脫離 bridge 生命週期）
    # 註：不可用 DETACHED_PROCESS——console 程式（python.exe）會被 Windows 新開一個可見黑窗
    if sys.platform == "win32":
        creation_flags = subprocess.CREATE_NO_WINDOW | subprocess.CREATE_NEW_PROCESS_GROUP
        _ = subprocess.Popen(
            command,
            cwd=str(services_dir),
            creationflags=creation_flags,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
    else:
        _ = subprocess.Popen(
            command,
            cwd=str(services_dir),
            start_new_session=True,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )

    # 等待 daemon 登記（上限 context.DAEMON_WAKE_WAIT_SEC——收窄「未登記又被叫醒」的窗口）
    wait_start_at = time.time()
    while time.time() - wait_start_at < context.DAEMON_WAKE_WAIT_SEC:
        if is_daemon_alive(agent_daemon):
            return {"ok": True}
        time.sleep(0.1)

    return {"ok": False, "error": "daemon start timeout"}

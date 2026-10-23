#!/usr/bin/env python3
"""
daemon_wake.py — 現行引擎 daemon 拉起（wake）。

職責：
- wake_daemon()：確保現行 agent daemon 運行——未運行則以 detached 方式拉起。
  （供 server_daemon 的 add_message 呼叫；不對外開路由——外部不得直呼）

說明：
- 現行引擎讀 runtime.json 動態值＞靜態 SSOT daemon_engine（值＝同名套件；缺／唔合法回 aider_daemon）
- 啟動命令與 PHP 端一致：python -m {daemon_engine}.main <runtime.json>（cwd = services）
- runtime 路徑＝context.RUNTIME_CONFIG_FILE（data/runtime.json，食死）
- Python 執行檔由機器層設定提供（runtime external_paths.python_executable——PHP 注入）
"""

import subprocess
import sys
import time
from pathlib import Path

from .. import context
from ..runtime import sc_config, sc_logger
from .daemon_registry import is_daemon_alive


def wake_daemon() -> dict:
    """確保現行引擎 daemon 運行；未運行則拉起（detached——不隨 bridge 生命週期）。"""
    # 現行引擎（runtime.json 動態值＞靜態 SSOT——值＝同名套件）
    agent_daemon = str(sc_config.get_config("daemon_engine") or "")

    # 判定：runtime.json 未設 daemon_engine → 記 log 並拒絕（唔代佢揀）
    if agent_daemon == "":
        sc_logger.log("wake_daemon failed: daemon_engine missing in runtime.json")
        return {"ok": False, "error": "missing daemon_engine"}

    # 判定：已在運行 → 不重複拉起
    if is_daemon_alive(agent_daemon):
        return {"ok": True}

    runtime_config_path = str(context.RUNTIME_CONFIG_FILE)
    python_executable   = str(sc_config.get_config("external_paths", "python_executable") or "")

    # 判定：前置缺失（無法拉起）→ 回報失敗
    if python_executable == "":
        return {"ok": False, "error": "missing python_executable"}

    # 套件根目錄（bridge/agent_daemon/daemon_wake.py → services）
    services_dir = Path(__file__).resolve().parents[2]

    command = [python_executable, "-u", "-m", agent_daemon + ".main", runtime_config_path]

    # 判定：Windows → 無視窗＋新程序群組；POSIX → 新 session（皆脫離 bridge 生命週期）
    # 註：不可用 DETACHED_PROCESS——console 程式（python.exe）會被 Windows 新開一個可見黑窗
    if sys.platform == "win32":
        creation_flags = subprocess.CREATE_NO_WINDOW | subprocess.CREATE_NEW_PROCESS_GROUP
        subprocess.Popen(
            command,
            cwd=str(services_dir),
            creationflags=creation_flags,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
    else:
        subprocess.Popen(
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

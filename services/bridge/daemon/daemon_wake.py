#!/usr/bin/env python3
"""
daemon_wake.py — 現行引擎 daemon 拉起（wake）。

職責：
- wake_daemon(config)：確保現行引擎 daemon 運行——未運行則以 detached 方式拉起。
  （供 server_daemon 的 add_message 呼叫；不對外開路由——外部不得直呼）

說明：
- 現行引擎讀 runtime.json 動態值＞靜態 SSOT daemon_engine（值＝同名套件；缺／唔合法回 aider_daemon）
- 啟動命令與 PHP 端一致：python -m {daemon_engine}.main <runtime.json>（cwd = services）
- runtime 路徑由 bridge 啟動時注入（config["runtime_config_path"]）
- Python 執行檔由機器層設定提供（runtime external_paths.python_executable——PHP 注入）
"""

import subprocess
import sys
import time
from pathlib import Path

from .daemon_registry import is_daemon_alive
from ..runtime.sc_config import read_daemon_engine

# 拉起後等待 daemon 註冊的上限（秒——供回應準確；逾時仍算「已送出拉起」）
_WAIT_REGISTER_SEC = 2.0


def wake_daemon(config: dict) -> dict:
    """確保現行引擎 daemon 運行；未運行則拉起（detached——不隨 bridge 生命週期）。"""
    # 現行引擎（runtime.json 動態值＞靜態 SSOT——值＝同名套件）
    daemon_name = read_daemon_engine(config)

    # 判定：已在運行 → 不重複拉起
    if is_daemon_alive(config, daemon_name):
        return {"started": False, "alive": True}

    runtime_config_path = config.get("runtime_config_path", "")
    python_executable   = config.get("external_paths", {}).get("python_executable", "")

    # 判定：前置缺失（無法拉起）→ 回報失敗
    if runtime_config_path == "" or python_executable == "":
        return {"started": False, "alive": False, "error": "missing runtime_config_path or python_executable"}

    # 套件根目錄（bridge/daemon/daemon_wake.py → services）
    services_dir = Path(__file__).resolve().parents[2]

    command = [python_executable, "-u", "-m", daemon_name + ".main", runtime_config_path]

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

    # 等待 daemon 寫入 registry（上限 _WAIT_REGISTER_SEC——讓回應的 alive 準確）
    wait_start_at = time.time()
    while time.time() - wait_start_at < _WAIT_REGISTER_SEC:
        if is_daemon_alive(config, daemon_name):
            return {"started": True, "alive": True}
        time.sleep(0.1)

    return {"started": True, "alive": False}

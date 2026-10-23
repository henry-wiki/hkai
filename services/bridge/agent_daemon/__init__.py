#!/usr/bin/env python3
"""
agent_daemon 套件——喚醒 agent daemon（bridge 側）。

    daemon_registry.py    掃心跳檔判斷 daemon 是否生存（父程序＝本 bridge）
    daemon_wake.py        未運行 → detached 拉起現行 agent daemon
"""

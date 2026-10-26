#!/usr/bin/env python3
"""
identity.py — 程序身份常數（daemon／worker／agent 單一來源）

職責：
- 集中三個程序身份常量（名＋標籤）
- 只提供常量，唔做任何 I/O

上游：context（daemon 身份）／heartbeat_worker／heartbeat_agent／process.record_spawn（worker 名字）／job_scheduler／job_exit
下游：無（純常量檔）
"""

# daemon 身份（心跳／台帳／單一實例守衛用）
DAEMON_PROGRAM_NAME = "goose_daemon"
DAEMON_PROGRAM_LABEL = "goose_daemon.main"

# 工人身份（台帳／心跳檔名用）
WORKER_PROGRAM_NAME = "worker"
WORKER_PROGRAM_LABEL = "goose_daemon.warm_worker"

# agent身份（agent心跳檔名／台帳用）
AGENT_PROGRAM_NAME = "goose"
AGENT_PROGRAM_LABEL = "goose_entry.acp"

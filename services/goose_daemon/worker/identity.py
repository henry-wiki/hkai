#!/usr/bin/env python3
"""
identity.py — 工人身份文件（worker 程序專用）

職責：
- 集中工人／引擎身份常量（單一來源）
- 只提供常量，唔改 context（共享值一律不動）

上游：runner／heartbeat_worker／process.record_spawn（讀常量）
下游：無（純常量檔）

將來工人要覆寫其他共享值（心跳節奏、角色…）一律加喺呢個檔，唔好散落 runner。
"""

# 工人身份（台帳／心跳檔名用）
WORKER_PROGRAM_NAME = "worker"
WORKER_PROGRAM_LABEL = "goose_daemon.warm_worker"

# 引擎身份（引擎心跳檔名／台帳用）
ENGINE_PROGRAM_NAME = "goose"
ENGINE_PROGRAM_LABEL = "goose_entry.acp"

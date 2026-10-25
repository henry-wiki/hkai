#!/usr/bin/env python3
"""
worker/engine — worker 專用引擎層（goose ACP／session 同步／引擎私有 context）。

上游：worker/job_executor.py
下游：acp SDK、goose 執行檔、runtime
"""

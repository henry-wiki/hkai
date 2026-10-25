#!/usr/bin/env python3
"""
preflight.py

goose 開工前預檢（純函數——唔改任何狀態）。

職責：
- build_goose_env()           model_name → goose 子程序環境（白名單／model 名）
- resolve_api_key_env_name()  model_name → key 環境變數名（"" ＝ 唔需要 key）
- check()                     逐項預檢；全部通過回 env，失敗 raise PreflightError（message＋detail）

上游：engine_entry.execute_job()（每張工單一次）
下游：engine_context.PROVIDER_TABLE／resolve_executable()；message_store()（project_slug）
"""

import os
from pathlib import Path

from . import engine_context


def build_goose_env(model_name: str):
    """由 model_name（litellm 式 provider/model）組 goose 子程序環境；唔支持＝None。"""
    # provider 前綴（第一個 "/" 前）＋模型名（後——可再含 "/"）
    model_parts = model_name.partition("/")
    provider_prefix = model_parts[0]
    model_part = model_parts[2]

    # 判定：唔喺白名單 → None
    if provider_prefix not in engine_context.PROVIDER_TABLE:
        return None

    # 判定：模型名缺失 → None
    if model_part == "":
        return None

    goose_env = dict(os.environ)
    goose_env["GOOSE_PROVIDER"] = provider_prefix
    goose_env["GOOSE_MODEL"] = model_part

    return goose_env


def resolve_api_key_env_name(model_name: str) -> str:
    """model_name 前綴 → key 環境變數名（"" ＝ 本機／免 key provider）。"""
    provider_prefix = model_name.partition("/")[0]

    return str(engine_context.PROVIDER_TABLE[provider_prefix]["api_key_env"])


class PreflightError(RuntimeError):
    """預檢失敗（message／detail 供上報——呼叫端記 failed 收工）。"""

    def __init__(self, message: str, detail: dict | None = None):
        super().__init__(message)
        self.message = message
        self.detail = detail if detail is not None else {}


def check(model_name: str, project_path: str) -> dict:
    """
    逐項預檢（唔改狀態）。

    回 goose_env（dict）＝通過；失敗 raise PreflightError。
    """
    goose_env = build_goose_env(model_name)

    # 判定：model_name 唔支持（前綴唔喺白名單／冇 model 名）
    if goose_env is None:
        raise PreflightError(
            "Unsupported model for goose engine (not in PROVIDER_TABLE)",
            {"model_name": model_name},
        )

    api_key_env_name = resolve_api_key_env_name(model_name)

    # 判定：需要 key 但未載入（oauth keys 檔缺對應 _API_KEY）
    if api_key_env_name != "" and not goose_env.get(api_key_env_name, ""):
        raise PreflightError(
            api_key_env_name + " missing (oauth keys file)",
            {"model_name": model_name},
        )

    # 判定：goose 執行檔缺席
    if engine_context.resolve_executable() == "":
        raise PreflightError(
            "goose executable not found (context.GOOSE_EXECUTABLE / PATH)",
        )

    # 判定：工作目錄缺失／唔存在（goose 要真實專案根）
    if project_path == "" or not Path(project_path).is_dir():
        raise PreflightError(
            "project path missing: " + project_path,
            {"project_path": project_path},
        )

    return goose_env

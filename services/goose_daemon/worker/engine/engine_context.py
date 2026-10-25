#!/usr/bin/env python3
"""
engine_context.py — goose 引擎私有 context（路徑＋常數；引擎專屬，唔放共用 context）

職責：
- resolve_path_root()                      goose 資料根（每專案一個資料夾——GOOSE_PATH_ROOT）
- resolve_executable()                     goose 執行檔（.env goose_executable；缺＝PATH 搜尋）
- verify_executable()                      真跑 --version 驗證執行檔（損壞／權限／架構錯）
- PROVIDER_TABLE                           model_name 前綴 → {kind, api_key_env}（唯一白名單；加 provider＝加一行）
- CUSTOM_PROVIDER_TABLE                    goose 原生唔識嘅 provider 細節（寫 custom_providers/<前綴>.json）
- PERMISSION_DECISION_POLL_INTERVAL_SEC    權限決定輪詢間隔

上游：engine_entry／conversation_sync（同一個資料根——唔再各自實作）
下游：context.LOCALDB_DIR（資料根）／context.message_store()（project_slug）
"""

import shutil
import subprocess
from pathlib import Path

from ...context import LOCALDB_DIR, daemon, message_store
from ...db import db_context
from ...runtime import sc_env

# 權限決定輪詢間隔（秒——ACP 權限等待每 tick 檢查一次決定檔）
PERMISSION_DECISION_POLL_INTERVAL_SEC = 0.4

# 【重要】下面兩個表嘅 api_key_env 係「環境變數名」（例：OPENROUTER_API_KEY），唔係 API key 本身。
# 真 key 只放 .env，由 sc_env.load_api_keys() 注入程序環境；代碼內永遠唔寫 key 值。

# 自訂 provider 表（model_name 前綴 → goose 原生唔識嘅 provider——每次發問寫
# <GOOSE_PATH_ROOT>/config/custom_providers/<前綴>.json；新增 provider＝加一筆）
CUSTOM_PROVIDER_TABLE = {
    "kilo": {
        "display_name": "Kilo",
        "description":  "Kilo AI Gateway",
        "api_key_env":  "KILO_API_KEY",
        "base_url":     "https://api.kilo.ai/api/gateway/chat/completions",
    },
    "deepseek": {
        "display_name": "DeepSeek",
        "description":  "DeepSeek API",
        "api_key_env":  "DEEPSEEK_API_KEY",
        "base_url":     "https://api.deepseek.com/chat/completions",
    },
}


# provider 白名單（model_name 前綴 → 種類＋key 環境變數；加新 provider 只加一行）
# api_key_env="" ＝ 唔需要 key（本機模型／OAuth 類）
PROVIDER_TABLE = {
    "openrouter": {"kind": "native", "api_key_env": "OPENROUTER_API_KEY"},
    "anthropic":  {"kind": "native", "api_key_env": "ANTHROPIC_API_KEY"},
    "openai":     {"kind": "native", "api_key_env": "OPENAI_API_KEY"},
    "google":     {"kind": "native", "api_key_env": "GOOGLE_API_KEY"},
    "xai":        {"kind": "native", "api_key_env": "XAI_API_KEY"},
    "litellm":    {"kind": "native", "api_key_env": "LITELLM_API_KEY"},
    "ollama":     {"kind": "native", "api_key_env": ""},
}

# 未開（goose 支援，但要多把 key／OAuth／CLI 安裝，唔符合單一 api_key_env 模型）：
#   bedrock（AWS_*）／azure（AZURE_*）／azure_foundry／databricks／snowflake／
#   gcpvertexai（GCP_*）／sagemaker／copilot／chatgpt_codex／cursor_agent／
#   claude_acp／codex_acp／其餘 OpenAI-compatible 聚合商（可改用 custom_providers）

# 自訂 provider 自動併入白名單（kind=custom——細節喺 CUSTOM_PROVIDER_TABLE）
PROVIDER_TABLE.update({
    provider_prefix: {"kind": "custom", "api_key_env": provider_conf["api_key_env"]}
    for provider_prefix, provider_conf in CUSTOM_PROVIDER_TABLE.items()
})


def resolve_path_root(project_path: str) -> Path:
    """goose 資料根（GOOSE_PATH_ROOT——每專案一個資料夾；session／config／state 全歸該專案）。"""
    project_slug = message_store().get_project_slug()

    # 判定：無 slug → 退資料夾名（舊資料／未帶 slug）
    if project_slug == "":
        project_slug = Path(project_path).name

    return LOCALDB_DIR / "goose" / project_slug


def resolve_storage_dir() -> str:
    """storage 根（runtime.json storage_dir——我們資料；非 goose schema）。"""
    return str(daemon().get_daemon_conf_data().get("storage_dir", ""))


def resolve_chat_db_file() -> Path:
    """我們 SQLite（hkai_chat_data.sqlite；剝 Windows 裝置前綴——sqlite URI 前置）。"""
    storage_path_text = resolve_storage_dir().replace("\\", "/")

    # 判定：帶 Windows 裝置前綴（"//./"）→ 剝走（sqlite URI 解析會誤當 authority）
    storage_path_text = storage_path_text.removeprefix("//./")

    # 判定：帶 Windows 長路徑前綴（"//?/"）→ 剝走（同上）
    storage_path_text = storage_path_text.removeprefix("//?/")

    return Path(storage_path_text) / "localdb" / db_context.CHAT_DB_FILENAME


def resolve_checkpoints_dir() -> Path:
    """checkpoints 根（功能開關：存在＝開、刪走＝關——我們同步狀態，goose 唔知）。"""
    return Path(resolve_storage_dir()) / "localdb" / "checkpoints"


def resolve_checkpoint_file(project_slug: str, chat_sid: str) -> Path:
    """該對話的快照檔（checkpoints/<project_slug>/<chat_sid>.json——目錄由寫檔自動建立）。"""
    return resolve_checkpoints_dir() / project_slug / (chat_sid + ".json")


def resolve_sessions_db_file(project_path: str) -> Path:
    """該專案 goose session DB（官方佈局：<GOOSE_PATH_ROOT>/data/sessions/sessions.db）。"""
    return resolve_path_root(project_path) / "data" / "sessions" / db_context.SESSIONS_DB_FILENAME


def resolve_executable() -> str:
    """goose 執行檔：.env goose_executable（sc_env 唯讀）；缺／檔唔存在＝PATH 搜尋。"""
    goose_executable = str(sc_env.get_env("goose_executable") or "")

    # 判定：.env 有值且檔案存在 → 用之
    if goose_executable != "" and Path(goose_executable).is_file() is True:
        return goose_executable

    # 退回 PATH 搜尋（shutil.which——跨平台）
    return shutil.which("goose") or ""


def verify_executable() -> tuple:
    """真跑 goose --version 驗證執行檔（回 (是否可用, 版本或錯誤)；成本低——每次 worker 開機一次）。"""
    goose_executable = resolve_executable()

    # 判定：連路徑都解析唔到 → 不可用
    if goose_executable == "":
        return False, "not found (config/PATH)"

    try:
        completed = subprocess.run(
            [goose_executable, "--version"],
            capture_output=True,
            text=True,
            check=False,
            timeout=10,
        )
    except (OSError, subprocess.SubprocessError) as exc:
        return False, str(exc)

    # 判定：非零退出 → 不可用（回傳錯誤輸出）
    if completed.returncode != 0:
        return False, (completed.stderr or completed.stdout or "").strip()

    return True, completed.stdout.strip()

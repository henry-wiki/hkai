#!/usr/bin/env python3
"""
context.py

統一橋接層（Service Locator）——集中管理全局單例與上下文注入。

其他模組只需 `from .context import xxx`，無需關心底層初始化細節。
"""

# 運行設定快取（首次 config() 解析一次——改 env 需重啟程序）
_config_cache = None


# ==========================================
# 統一獲取全局單例（Bridging Functions）
# ==========================================
def agent():
    """取得 acp_agent_class 單例。"""
    from .core import acp_agent_class  # 延遲——避免循環 import
    return acp_agent_class.get_instance()


def config() -> dict:
    """運行設定（環境變數——首次呼叫解析一次）。

    key：
        model_name    aider 模型（AIDER_ACP_MODEL——必填先跑得 prompt）
        edit_format   edit 格式覆寫（AIDER_ACP_EDIT_FORMAT——空＝模型預設）
        auto_yes      自動模式 yes（AIDER_ACP_AUTO_YES——permission 模組缺席時用；默認 False）
        env_file      env 檔路徑（AIDER_ACP_ENV_FILE——有設＝先把該檔補入 os.environ）
    """
    global _config_cache

    # 判定：未解析過 → 解析（其後直回快取）
    if _config_cache is None:
        _config_cache = _load_config()

    return _config_cache


def _load_config() -> dict:
    """解析環境變數（可選先載 env 檔——python-dotenv，同 daemon 家族同一支）。"""
    import os

    env_file = os.environ.get("AIDER_ACP_ENV_FILE", "")

    # 判定：有指定 env 檔 → 先行補入（唔蓋現有 env——缺先補）
    if env_file:
        from dotenv import dotenv_values

        env_values = dotenv_values(env_file, interpolate=False)

        for env_key, env_value in env_values.items():
            # 判定：值非空且未設 → 補入 os.environ
            if env_value and env_key not in os.environ:
                os.environ[env_key] = env_value

    return {
        "model_name":  os.environ.get("AIDER_ACP_MODEL", ""),
        "edit_format": os.environ.get("AIDER_ACP_EDIT_FORMAT", ""),
        "auto_yes":    os.environ.get("AIDER_ACP_AUTO_YES", "").lower() in ("1", "true", "yes"),
        "env_file":    env_file,
    }

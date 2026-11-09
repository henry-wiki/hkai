#!/usr/bin/env python3
"""
chat_mode.py

UI 模 → agent原生模式對映（agent側唯一來源）。

上游：runner.init()（worker 開機建表，runner:92）
      entry（每張工單 get_chat_mode → GOOSE_MODE）／conversation_sync（建 session 寫 goose_mode）
下游：runtime/sc_config（讀 runtime.json 本agent組）、bootstrap/sc_logger（記錄）

契約：
    agent組＝config.get_config(PROGRAM_NAME)（現行 goose_daemon）
    chat_mode 條目＝{ "<UI 模名>": {"mode": "<goose 原生模式>", "description": "..."} }
    未對映 → DEFAULT_MODE（goose 自身默認）
"""

from typing import cast

from ...context import PROGRAM_NAME
from bootstrap import config
from bootstrap import sc_logger

# 未對映時嘅agent默認模式（goose 自身默認）
DEFAULT_MODE = "auto"

# 已建表（UI 模名 → 原生模式；init() 建立——config 重載後重建成）
_chat_mode_map: dict[str, str] = {}


def init() -> dict[str, str]:
    """依本agent組建 chat_mode 對映（config 載入後呼叫；回傳新表）。"""
    global _chat_mode_map
    _chat_mode_map = _init_chat_chat_mode_map()
    return _chat_mode_map


def get_chat_mode(chat_mode: str) -> str:
    """UI 模名 → 原生模式；未對映 → DEFAULT_MODE（並記一行）。"""
    _chat_mode = str(_chat_mode_map.get(chat_mode, ""))

    # 判定：未對映（缺／詞彙唔夾）→ agent默認
    if _chat_mode == "":
        sc_logger.log("chat mode not mapped for " + PROGRAM_NAME + ": '" + chat_mode + "'")
        return DEFAULT_MODE

    return _chat_mode


def _init_chat_chat_mode_map() -> dict[str, str]:
    """讀本agent組（config.get_config(PROGRAM_NAME)）→ UI 模名 → 原生模式。"""
    agent_config = config.get_config(PROGRAM_NAME)

    # 判定：agent組缺／非 dict → 空表（agent自身默認）
    if not isinstance(agent_config, dict):
        return {}

    agent_config_map = cast("dict[str, object]", agent_config)
    chat_mode_config = agent_config_map.get("chat_mode", {})

    # 判定：chat_mode 缺／非 dict → 空表（agent自身默認）
    if not isinstance(chat_mode_config, dict):
        return {}

    chat_mode_config_map = cast("dict[str, object]", chat_mode_config)
    chat_chat_mode_map: dict[str, str] = {}

    # 逐個 UI 模條目抽原生模式（value 有 mode 欄＝UI 模）
    for chat_mode_name, chat_mode_item in chat_mode_config_map.items():
        # 判定：非 UI 模條目（唔係 dict）→ 跳
        if not isinstance(chat_mode_item, dict):
            continue

        chat_mode_item_map = cast("dict[str, object]", chat_mode_item)

        # 判定：無 mode 欄 → 跳
        if "mode" not in chat_mode_item_map:
            continue

        chat_chat_mode_map[chat_mode_name] = str(chat_mode_item_map["mode"])

    return chat_chat_mode_map

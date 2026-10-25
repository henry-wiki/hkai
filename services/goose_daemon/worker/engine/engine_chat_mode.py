#!/usr/bin/env python3
"""
engine_chat_mode.py

UI 模 → 引擎原生模式對映（引擎側唯一來源）。

上游：daemon_manager.load_config（每次 config 載入後 init() 建表）
      engine_entry（每張工單 get_engine_chat_mode → GOOSE_MODE）／conversation_sync（建 session 寫 goose_mode）
下游：runtime/sc_config（讀 runtime.json 本引擎組）、runtime/sc_logger（未對映記行）

契約：
    引擎組＝sc_config.get_config(PROGRAM_NAME)（現行 goose_daemon）
    chat_mode 條目＝{ "<UI 模名>": {"mode": "<goose 原生模式>", "description": "..."} }
    未對映 → DEFAULT_MODE（goose 自身默認）
"""

from ...context import PROGRAM_NAME
from ...runtime import sc_config, sc_logger

# 未對映時嘅引擎默認模式（goose 自身默認）
DEFAULT_MODE = "auto"

# 已建表（UI 模名 → 原生模式；init() 建立——config 重載後重建成）
_chat_mode_map = {}


def init() -> dict[str, str]:
    """依本引擎組建 chat_mode 對映（config 載入後呼叫；回傳新表）。"""
    global _chat_mode_map
    _chat_mode_map = _init_chat_chat_mode_map()
    return _chat_mode_map


def get_engine_chat_mode(chat_mode: str) -> str:
    """UI 模名 → 原生模式；未對映 → DEFAULT_MODE（並記一行）。"""
    _chat_mode = str(_chat_mode_map.get(chat_mode, ""))

    # 判定：未對映（缺／詞彙唔夾）→ 引擎默認
    if _chat_mode == "":
        sc_logger.log("chat mode not mapped for " + PROGRAM_NAME + ": '" + chat_mode + "'")
        return DEFAULT_MODE

    return _chat_mode


def _init_chat_chat_mode_map() -> dict:
    """讀本引擎組（sc_config.get_config(PROGRAM_NAME)）→ UI 模名 → 原生模式。"""
    engine_config = sc_config.get_config(PROGRAM_NAME)

    # 判定：引擎組缺／非 dict → 空表（引擎自身默認）
    if not isinstance(engine_config, dict):
        return {}

    chat_mode_config = engine_config.get("chat_mode", {})
    chat_chat_mode_map = {}

    # 逐個 UI 模條目抽原生模式（value 有 mode 欄＝UI 模）
    for chat_mode_name, chat_mode_item in chat_mode_config.items():
        # 判定：非 UI 模條目（唔係 dict）→ 跳
        if not isinstance(chat_mode_item, dict):
            continue
        # 判定：無 mode 欄 → 跳
        if "mode" not in chat_mode_item:
            continue
        chat_chat_mode_map[chat_mode_name] = str(chat_mode_item["mode"])

    return chat_chat_mode_map

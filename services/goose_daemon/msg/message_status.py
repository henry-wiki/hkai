#!/usr/bin/env python3
"""
message_status.py

狀態機（FSM）——唯一的 msg_status 寫入者。

SSOT：web/sc_config/hkai/fsm_map.json → status（order／terminal／transitions）

規則：
- 只允許 transitions 定義的 (status, event) → next_status；未定義＝非法（不改 status）
- 終態（completed／failed／cancelled）不可再轉出（late event 忽略）
- 合法轉移才寫 msg_status（append——最新 status 在最後一行；單一詞彙——無翻譯）
- 單一入口：apply(event) 寫狀態；冇 IPC／回報通道（暖 worker 同程序）
"""

import json
from pathlib import Path
from typing import cast

from ..context import message_store

# fsm_map 路徑（本檔住 <工作區根>/services/goose_daemon/msg/——回升 web/sc_config/hkai）
_FSM_MAP_PATH = Path(__file__).resolve().parents[3] / "web" / "sc_config" / "hkai" / "fsm_map.json"


class message_status_class:
    """狀態機（singleton——整個程序共用）。"""

    _instance: "message_status_class | None" = None
    _terminal_status: tuple[str, ...]
    _status_order: tuple[str, ...]
    _transitions: dict[tuple[str, str], str]
    _status_events: set[str]
    _loaded: bool = False

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is not None:
            return cls._instance

        cls._instance = super().__new__(cls)
        return cls._instance

    def __init__(self) -> None:
        """初始化（首次建立才載入轉移表——單例重複呼叫唔重載）。"""
        # 判定：已載入 → 唔重載
        if self._loaded is True:
            return

        self._terminal_status = ()
        self._status_order = ()
        self._transitions = {}
        self._status_events = set()

        self._load_fsm_map()

        self._loaded = True

    @classmethod
    def get_instance(cls) -> "message_status_class":
        """取得 message_status_class 單例。"""
        return cls()

    def _load_fsm_map(self) -> None:
        """載入 fsm_map（SSOT）：terminal／transitions。"""
        fsm_map = cast("dict[str, object]", json.loads(_FSM_MAP_PATH.read_text(encoding="utf-8")))
        status_map = cast("dict[str, object]", fsm_map["status"])

        self._terminal_status = tuple(cast("list[str]", status_map["terminal"]))
        self._status_order    = tuple(cast("list[str]", status_map["order"]))

        # (from_state, event) -> to_state（"*" 為萬用來源——transitions 喺頂層）
        self._transitions = {}

        # 逐條登錄轉移
        for transition_item in cast("list[dict[str, str]]", fsm_map["transitions"]):
            transition_key = (transition_item["from"], transition_item["event"])
            self._transitions[transition_key] = transition_item["to"]

        # 有轉移定義的事件集合（＝會驅動狀態——供 log 分類 state／trace）
        self._status_events = {transition_key[1] for transition_key in self._transitions}

        # 檔案＝status（單一詞彙——本機器嘅狀態源；全鏈同一粒值）

    def is_status_event(self, event_name: str) -> bool:
        """此事件是否會驅動 status（有轉移定義）。"""
        return event_name in self._status_events

    # 此 status 是否終態（fsm_map status.terminal——唯一來源）
    def is_terminal_status(self, status: str) -> bool:
        return status in self._terminal_status

    # 契約 status 清單（fsm_map status order＋terminal——唯一來源）
    def get_status_values(self) -> tuple[str, ...]:
        return self._status_order + self._terminal_status

    def get_status(self) -> str:
        """目前 status（讀 msg_status 檔——檔案即 status）。"""
        return message_store().get_msg_status()

    def apply(self, event_name: str) -> str:
        """套用事件：合法則轉移並寫 msg_status；回傳（可能未變的）狀態。"""
        _current_status = self.get_status()

        # 判定：終態 → 不再轉出（late event 忽略）
        if _current_status in self._terminal_status:
            _next_status = _current_status
        else:
            _next_status = self._resolve_next_status(_current_status, event_name)

        # 判定：尚未落地（status 檔不存在）→ 先寫目前 status（建立第一行）
        if not message_store().get_msg_status_path().exists():
            message_store().set_msg_status(_current_status)

        # 判定：status 改變 → 寫入
        if _next_status != _current_status:
            message_store().set_msg_status(_next_status)

        return _next_status

    def _resolve_next_status(self, current_status: str, event_name: str) -> str:
        """依轉移表解析下一 status（未定義 → 維持現狀）。"""
        _next_status = self._transitions.get((current_status, event_name))

        # 判定：無精確轉移 → 查萬用來源 ("*", event)
        if _next_status is None:
            _next_status = self._transitions.get(("*", event_name))

        # 判定：未定義的 (status, event) → 非法，不改 status
        if _next_status is None:
            return current_status

        return _next_status

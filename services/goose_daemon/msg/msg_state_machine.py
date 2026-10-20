#!/usr/bin/env python3
"""
msg_state_machine.py

狀態機（FSM）——唯一的 msg_status 寫入者。

SSOT：web/sc_config/hkai/fsm_map.json → status（order／terminal／transitions）

規則：
- 只允許 transitions 定義的 (status, event) → next_status；未定義＝非法（不改 status）
- 終態（completed／failed／cancelled）不可再轉出（late event 忽略）
- 合法轉移才寫 msg_status（append——最新 status 在最後一行；單一詞彙——無翻譯）
- 跨程序：FSM 為唯一狀態管理者；子程序自行 apply(event)＋report(event)（IPC 通道藏在 FSM 內，應用層不碰）
"""

import json
import multiprocessing
import queue
from pathlib import Path

from ..context import msg_main

# fsm_map 路徑（本檔住 <工作區根>/services/goose_daemon/msg/——回升 web/sc_config/hkai）
_FSM_MAP_PATH = Path(__file__).resolve().parents[3] / "web" / "sc_config" / "hkai" / "fsm_map.json"


class msg_state_machine_class:
    """狀態機（singleton——整個程序共用）。"""

    _instance: "msg_state_machine_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次（載入轉移表）。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
            cls._instance._load_fsm_map()
            cls._instance._channel = None
        return cls._instance

    @classmethod
    def get_instance(cls) -> "msg_state_machine_class":
        """取得 msg_state_machine_class 單例。"""
        return cls()

    def create_channel(self):
        """建立跨程序事件通道（父程序——整個 run 一次）。"""
        self._channel = multiprocessing.Queue()
        return self._channel

    def attach_channel(self, channel) -> None:
        """綁定跨程序事件通道（子程序——由 spawn args 傳入）。"""
        self._channel = channel

    def get_channel(self):
        """取得跨程序事件通道（父程序——傳給子程序用）。"""
        return self._channel

    def report(self, event_name: str) -> None:
        """回報事件（子程序——只送通道喚醒父程序；狀態由 FSM 統一管理）。"""
        # 判定：無通道（暖模式同程序執行）→ 只套用不報告
        if self._channel is None:
            return
        self._channel.put(event_name)

    def apply_and_report(self, event_name: str) -> str:
        """子程序用：先套用事件（經 FSM 寫狀態），再回報父程序（喚醒）。"""
        _next_status = self.apply(event_name)
        self.report(event_name)
        return _next_status

    def wait_event(self, timeout: float) -> str:
        """等待子程序事件（父程序——即時；逾時回空字串）。"""
        try:
            return self._channel.get(timeout=timeout)
        except queue.Empty:
            return ""

    def close_channel(self) -> None:
        """關閉通道並等待送出（子程序結束前 flush——避免 os._exit 丟失事件）。"""
        # 判定：無通道（暖模式同程序執行）→ 無事可做
        if self._channel is None:
            return
        self._channel.close()
        self._channel.join_thread()

    def _load_fsm_map(self) -> None:
        """載入 fsm_map（SSOT）：terminal／transitions。"""
        _fsm_map = json.loads(_FSM_MAP_PATH.read_text(encoding="utf-8"))
        _status = _fsm_map["status"]

        self._terminal_status = tuple(_status["terminal"])
        self._status_order    = tuple(_status["order"])

        # (from_state, event) -> to_state（"*" 為萬用來源——transitions 喺頂層）
        self._transitions = {}
        # 逐條登錄轉移
        for _transition in _fsm_map["transitions"]:
            _key = (_transition["from"], _transition["event"])
            self._transitions[_key] = _transition["to"]

        # 有轉移定義的事件集合（＝會驅動狀態——供 log 分類 state／trace）
        self._status_events = {_transition["event"] for _transition in _fsm_map["transitions"]}

        # 檔案＝status（單一詞彙——本機器嘅狀態源；全鏈同一粒值）

    def is_status_event(self, event_name: str) -> bool:
        """此事件是否會驅動 status（有轉移定義）。"""
        return event_name in self._status_events

    # 此 status 是否終態（fsm_map status.terminal——唯一來源）
    def is_terminal_status(self, status: str) -> bool:
        return status in self._terminal_status

    # 契約 status 清單（fsm_map status order＋terminal——唯一來源）
    def get_status_values(self) -> tuple:
        return self._status_order + self._terminal_status

    def get_status(self) -> str:
        """目前 status（讀 msg_status 檔——檔案即 status）。"""
        return msg_main().get_msg_status()

    def apply(self, event_name: str) -> str:
        """套用事件：合法則轉移並寫 msg_status；回傳（可能未變的）狀態。"""
        _current_status = self.get_status()

        # 判定：終態 → 不再轉出（late event 忽略）
        if _current_status in self._terminal_status:
            _next_status = _current_status
        else:
            _next_status = self._resolve_next_status(_current_status, event_name)

        # 判定：尚未落地（status 檔不存在）→ 先寫目前 status（建立第一行）
        if not msg_main().get_msg_status_path().exists():
            msg_main().set_msg_status(_current_status)

        # 判定：status 改變 → 寫入
        if _next_status != _current_status:
            msg_main().set_msg_status(_next_status)

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

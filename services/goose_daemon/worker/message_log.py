#!/usr/bin/env python3
"""
message_log.py

message_log 檔（data/messages/polling/{project_slug}/{message_id}/msg_log.txt）讀寫專屬（message_log_class singleton——get_instance() 取得）。

外殼（與 PHP polling_log_Class 同一格式）：
    datetime（Y-m-d H:i:s 本地時區）/ source / kind / level / event / message
    metadata（有值才寫）/ payload（有值才寫）
    message_id 由目錄名承載（chat_id 由 msg_conf 帶入），不寫入 log entry

    kind：state（有轉移定義——會驅動狀態）／trace（純足跡）
"""

import json
import threading
from pathlib import Path
from typing import cast

from ..context import message_store
from ..msg import message_context
from ..utils.io import get_tail_content
from ..utils.timer import timer_class


def _read_jsonl_entries(jsonl_path: Path) -> "list[dict[str, object]]":
    """讀 JSONL → list（空行跳過；每行一個 object）。"""
    # 判定：未有檔 → 空
    if jsonl_path.exists() is False:
        return []

    entry_list: "list[dict[str, object]]" = []

    for jsonl_line in jsonl_path.read_text(encoding="utf-8").splitlines():
        # 判定：空行 → 跳過
        if jsonl_line.strip() == "":
            continue

        entry_list.append(cast("dict[str, object]", json.loads(jsonl_line)))

    return entry_list


def _write_jsonl_entries(jsonl_path: Path, entry_list: "list[dict[str, object]]") -> None:
    """整檔重寫 JSONL（一行一個 object；LF）。"""
    jsonl_lines: "list[str]" = []

    for entry_item in entry_list:
        jsonl_lines.append(json.dumps(entry_item, ensure_ascii=False))

    with jsonl_path.open("w", encoding="utf-8", newline="") as output_file:
        _ = output_file.write("\n".join(jsonl_lines) + "\n")


def _parse_poll_file_identity(poll_file_name: str) -> "tuple[str, int, str]":
    """檔名 → (family, response_index, type)——msg_{family}.{i}.{type}.txt。"""
    stem = poll_file_name

    # 判定：有 msg_ 前綴 → 去掉
    if stem.startswith("msg_"):
        stem = stem[4:]

    # 判定：有 .txt 尾 → 去掉
    if stem.endswith(".txt"):
        stem = stem[:-4]

    identity_parts = stem.split(".")

    # 判定：格式唔合（少過 3 段）→ 回空身份
    if len(identity_parts) < 3:
        return "", 0, ""

    return identity_parts[0], int(identity_parts[1]), identity_parts[2]


class message_log_class:
    """
    message_log 檔的統一寫入/讀取（singleton——整個程序只有一個實例）。

    職責單一：message_log 檔案的「路徑解析＋寫入（JSONL append）＋讀取」；不含任何處理流程。
    """

    _instance: "message_log_class | None" = None
    # 串流追溯開關（預設開啟；set_stream_log() 控制）
    _stream_log_enabled: bool = True
    # 寫入鎖（callback 由多執行緒觸發——序列化寫入，避免行交錯；可重入——poll 更新整段持鎖再入寫檔）
    _write_lock: "threading.RLock" = threading.RLock()
    # 當前 agent turn id（session 每輪寫入；entry 建檔時記落去）
    _current_agent_turn_id: str = ""

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "message_log_class":
        """取得 message_log_class 單例（整個程序共用同一實例）。"""
        return cls()

    def _log_path(self) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "log")

    # 事件分類：state（有轉移定義——會驅動狀態）／trace（純足跡）
    def _get_event_kind(self, event: str) -> str:
        """查 FSM 轉移表判定事件分類（延遲 import——避免與 context 循環）。"""
        from ..msg.message_status import message_status_class
        return "state" if message_status_class.get_instance().is_status_event(event) else "trace"

    # 核心寫入（level 由呼叫端給——統一標準 JSONL append——與 PHP polling_log_Class 同一格式；回傳該行字串）
    def append_log(self, event: str, level: str, message: str, metadata: dict[str, object] | None = None, payload: dict[str, object] | None = None, detail: str = "", meta: dict[str, object] | None = None) -> str:
        """
        外殼：datetime（Y-m-d H:i:s 本地時區）/ source / kind / level / event / message
        metadata／payload（有值才寫——ERROR 層級不寫，只進 error_log）。
        message_id 由目錄名承載（chat_id 由 msg_conf 帶入），不寫入 log entry。

        detail：error_log 專用的完整內容（空 → 沿用 message）。
        """
        log_data: dict[str, object] = {
            "datetime": timer_class.get_datetime(),
            "source": "python-daemon",
            "kind": self._get_event_kind(event),
            "level": level,
            "event": event,
            "message": message,
        }

        # 判定：ERROR 層級 → 完整內容另寫 error_log；message_log 只留一句短話（不帶 metadata／payload）
        if level == "ERROR":
            _error_record: dict[str, object] = {}
            _error_record["datetime"] = log_data["datetime"]
            _error_record["event"] = event
            _error_record["message"] = detail or message
            _error_record["metadata"] = metadata
            self.append_error_log_record(_error_record)
        else:
            if metadata:
                log_data["metadata"] = metadata
            if payload:
                log_data["payload"] = payload
            # 附加數據（目前無消費端——照樣寫入）
            if meta:
                log_data["meta"] = meta

        log_line = json.dumps(log_data, ensure_ascii=False)
        # append 模式（不覆寫既有內容）+ CRLF 結尾（newline="" 避免 text mode 翻譯成 \r\r\n）
        # 多執行緒（主流程／記憶體守衛）→ 以鎖序列化寫入（避免行交錯）
        with self._write_lock, self._log_path().open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(log_line + "\r\n")

        return log_line

    # 語法糖：level 固定 INFO（event/message/metadata/payload 由呼叫端給）
    def append_info_log(self, event: str, message: str, metadata: dict[str, object] | None = None, payload: dict[str, object] | None = None, meta: dict[str, object] | None = None) -> str:
        return self.append_log(event, "INFO", message, metadata, payload, meta=meta)

    # 語法糖：level 固定 SUCCESS
    def append_success_log(self, event: str, message: str, metadata: dict[str, object] | None = None, payload: dict[str, object] | None = None, meta: dict[str, object] | None = None) -> str:
        return self.append_log(event, "SUCCESS", message, metadata, payload, meta=meta)

    # 語法糖：level 固定 ERROR（detail＝完整內容——僅寫入 error_log）
    def append_error_log(self, event: str, message: str, metadata: dict[str, object] | None = None, payload: dict[str, object] | None = None, detail: str = "", meta: dict[str, object] | None = None) -> str:
        return self.append_log(event, "ERROR", message, metadata, payload, detail, meta)

    # 語法糖：level 固定 WARN
    def append_warn_log(self, event: str, message: str, metadata: dict[str, object] | None = None, payload: dict[str, object] | None = None, meta: dict[str, object] | None = None) -> str:
        return self.append_log(event, "WARN", message, metadata, payload, meta=meta)

    # 設定串流追溯開關（預設開啟；set_stream_log(false) 可關閉）
    def set_stream_log(self, enabled: bool) -> None:
        self._stream_log_enabled = enabled

    def _stream_log_path(self) -> Path:
        """stream_log 檔路徑（與 message_log 同目錄）。"""
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "stream_log")

    # 串流追溯寫入（每個 callback 觸發寫一行 JSONL——datetime 置於最前）
    def append_stream_log(self, record: dict[str, object]) -> None:
        """寫入一行 stream_log JSONL 記錄（datetime 置前——main information）。"""
        if not self._stream_log_enabled:
            return
        # datetime 置於最前（record 其餘鍵依序接續）
        output_record: dict[str, object] = {"datetime": timer_class.get_datetime()}
        output_record.update(record)
        # newline="" 避免 text mode 把 \r\n 翻譯成 \r\r\n（導致 splitlines 誤判行界）
        # 多執行緒（主流程／摘要 worker／cache warming）→ 以鎖序列化寫入（避免行交錯）
        with self._write_lock, self._stream_log_path().open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(json.dumps(output_record, ensure_ascii=False, default=str) + "\r\n")

    def _error_log_path(self) -> Path:
        """error_log 檔路徑（僅 ERROR 事件——與 stream_log 同目錄）。"""
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "error_log")

    # error_log 寫入（僅 ERROR 事件——append JSONL）
    def append_error_log_record(self, record: dict[str, object]) -> None:
        """寫入一行 error_log JSONL（錯誤事件）。"""
        with self._write_lock, self._error_log_path().open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(json.dumps(record, ensure_ascii=False, default=str) + "\r\n")

    # response thinking / answer txt 路徑（索引制——與 stream_log 同目錄）
    def _response_thinking_path(self, response_index: int) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, f"response.{response_index}.thinking")

    def _response_result_path(self, response_index: int) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, f"response.{response_index}.result")

    def _response_tools_path(self, response_index: int) -> Path:
        message_dir = message_store().get_message_dir()
        return message_dir / f"msg_response.{response_index}.tools.jsonl"

    # 工具調用記錄（append 一行 JSONL——每輪一檔；start／done 兩行靠 tool_call_id 合）
    def append_tool_call(self, response_index: int, record: dict[str, object]) -> None:
        tools_path = self._response_tools_path(response_index)

        record_line = json.dumps(record, ensure_ascii=False)

        with tools_path.open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(record_line + chr(10))

        self._update_poll_list(tools_path)

    # response thinking 純文字 append（reasoning_content——帶 response index）
    def append_response_thinking(self, response_index: int, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        response_thinking_path = self._response_thinking_path(response_index)
        with response_thinking_path.open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(text)
        self._update_poll_list(response_thinking_path)

    # response result 純文字 append（content——帶 response index）
    def append_response_result(self, response_index: int, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        response_result_path = self._response_result_path(response_index)
        with response_result_path.open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(text)
        self._update_poll_list(response_result_path)

    # response replay 路徑（agent load 重播——獨立歸檔）
    def _response_replay_path(self, response_index: int) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, f"response.{response_index}.replay")

    # response replay 純文字 append（agent重播內容——帶 response index）
    # 刻意唔入 poll（_update_poll_list）：重播唔屬本輪回應——前端唔會拉、唔會顯示；此檔＝後端紀錄
    def append_response_replay(self, response_index: int, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        response_replay_path = self._response_replay_path(response_index)
        with response_replay_path.open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(text)

    # summarize thinking 路徑
    def _summarize_thinking_path(self) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "summarize.0.thinking")

    # summarize result 路徑
    def _summarize_result_path(self) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "summarize.0.result")

    # summarize thinking 純文字 append（reasoning_content）
    def append_summarize_thinking(self, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        summarize_thinking_path = self._summarize_thinking_path()
        with summarize_thinking_path.open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(text)
        self._update_poll_list(summarize_thinking_path)

    # summarize result 純文字 append（content）
    def append_summarize_result(self, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        summarize_result_path = self._summarize_result_path()
        with summarize_result_path.open("a", encoding="utf-8", newline="") as output_file:
            _ = output_file.write(text)
        self._update_poll_list(summarize_result_path)

    #---------------------------------------------------------
    # 由 session 設定當前 turn id（entry 建檔時記落去）
    def set_current_agent_turn_id(self, agent_turn_id: str) -> None:
        self._current_agent_turn_id = str(agent_turn_id)

    #---------------------------------------------------------
    # 回合表（msg_turn_list.jsonl——Python 獨家寫；每事件整檔重寫）
    # 1. 一行一回合（key＝agent_turn_id）
    # 2. 同一輪累加 thinking／result 字數、事件次數、最後時間
    #---------------------------------------------------------

    # 回合表路徑
    def _get_turn_list_path(self) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "turn_list")

    # 讀回合表（JSONL → list）
    def _read_turn_list(self) -> "list[dict[str, object]]":
        return _read_jsonl_entries(self._get_turn_list_path())

    # 寫回合表（整檔重寫——JSONL；LF）
    def _write_turn_list(self, turn_list: "list[dict[str, object]]") -> None:
        with self._write_lock:
            _write_jsonl_entries(self._get_turn_list_path(), turn_list)

    # 記一次事件（session 每事件叫一次；整檔重寫）
    def remember_agent_turn(self, agent_turn_id: str, response_index: int, update_kind: str, text_length: int) -> None:
        with self._write_lock:
            # 判定：冇 turn id → 唔記
            if agent_turn_id == "":
                return

            current_time = timer_class.get_datetime()

            turn_list = self._read_turn_list()
            turn_entry = None

            # 逐回合：找回本輪
            for turn_list_entry_item in turn_list:
                # 判定：同一 turn → 記低
                if turn_list_entry_item.get("agent_turn_id", "") == agent_turn_id:
                    turn_entry = turn_list_entry_item
                    continue

            # 判定：首次見到此輪 → 建條目
            if turn_entry is None:
                turn_entry = {
                    "agent_turn_id":  agent_turn_id,
                    "response_index": response_index,
                    "first_seen_at":  current_time,
                    "last_seen_at":   current_time,
                    "thinking_chars": 0,
                    "result_chars":   0,
                    "event_count":    0,
                }
                turn_list.append(turn_entry)

            turn_entry["last_seen_at"] = current_time
            turn_entry["event_count"] = int(turn_entry["event_count"]) + 1

            # 判定：思考段 → 累加 thinking 字數
            if update_kind == "thought":
                turn_entry["thinking_chars"] = int(turn_entry["thinking_chars"]) + text_length

            # 判定：回答段 → 累加 result 字數
            if update_kind == "text":
                turn_entry["result_chars"] = int(turn_entry["result_chars"]) + text_length

            self._write_turn_list(turn_list)

    # poll 對照表（msg_poll_list.jsonl——Python 獨家寫；PHP 純讀）
    # 1. 路徑：msg_poll_list.jsonl（與 PHP 同一檔名規則）
    # 2. 逐 append 更新：本檔 update 時間＋byte 長（狀態交 msg_status 單一來源）
    # 3. 收尾轉態：全數仍 poll → completed／aborted
    #---------------------------------------------------------

    # poll 對照表路徑
    def _get_poll_list_path(self) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "poll_list")

    # 讀 poll 對照表（逐行解析；舊制行——冇 poll_file——唔收）
    def _read_poll_list(self) -> list[dict[str, object]]:
        poll_list_path = self._get_poll_list_path()
        poll_list: "list[dict[str, object]]" = []

        # 逐行：舊制行（冇 poll_file——PHP 時代遺留）唔收
        for poll_list_entry in _read_jsonl_entries(poll_list_path):
            # 判定：冇 poll_file → 跳過
            if "poll_file" not in poll_list_entry:
                continue

            poll_list.append(poll_list_entry)

        return poll_list

    # 寫 poll 對照表（整檔重寫——JSONL；LF）
    def _write_poll_list(self, poll_list: list[dict[str, object]]) -> None:
        with self._write_lock:
            _write_jsonl_entries(self._get_poll_list_path(), poll_list)

    # 對照更新（一檔 append 完）：只更新本檔時間／長度（狀態交 msg_status 單一來源）
    def _update_poll_list(self, poll_file_path: Path) -> None:
        with self._write_lock:
            self._update_poll_list_locked(poll_file_path)

    # 對照更新本體（持鎖內——讀改寫一氣呵成）
    def _update_poll_list_locked(self, poll_file_path: Path) -> None:
        poll_list = self._read_poll_list()
        current_time = timer_class.get_datetime()
        file_content_length = poll_file_path.stat().st_size

        current_entry: dict[str, object] | None = None

        # 逐 entry：只取返本檔（狀態由 msg_status 決定——poll_list 唔准代判完成）
        for poll_list_entry in poll_list:
            # 判定：本檔 → 記低（下面更新）
            if poll_list_entry["poll_file"] == poll_file_path.name:
                current_entry = poll_list_entry
                continue

        family, response_index, response_type = _parse_poll_file_identity(poll_file_path.name)

        # 判定：本檔首次出現 → 起 entry（起始時間＝當下）
        if current_entry is None:
            current_entry = {
                "family":         family,
                "response_index": response_index,
                "type":           response_type,
                "agent_turn_id":  self._current_agent_turn_id,
                "poll_file":      poll_file_path.name,
                "status":         "poll",
                "start_at":       current_time,
                "update_at":      current_time,
                "finished_at":    "",
                "content_length": file_content_length,
            }
            poll_list.append(current_entry)

        # 本檔＝寫緊（poll）——update 時間＋byte 長（每 chunk 同步）
        # 狀態唔重設：新條目已起 poll（上面）；已收（completed／aborted）者唔回退——
        # 收尾後嘅遲到寫入（teardown flush）唔可以打返 poll
        current_entry["update_at"] = current_time
        current_entry["content_length"] = file_content_length

        # 判定：有 agent turn id 而條目未有 → 補上
        if self._current_agent_turn_id != "" and current_entry.get("agent_turn_id", "") == "":
            current_entry["agent_turn_id"] = self._current_agent_turn_id

        self._write_poll_list(poll_list)

    # 關閉指定輪（family=response；仍 poll 嘅 entry → 目標狀態＋完成時間）
    def close_response_turn(self, response_index: int, poll_status: str) -> None:
        with self._write_lock:
            self._close_poll_entries(poll_status, int(response_index))

    # 全數 poll 檔收 completed（訊息成功終態）
    def complete_all_poll_files(self) -> None:
        self._close_all_poll_files("completed")

    # 全數 poll 檔收 aborted（訊息失敗／取消終態）
    def abort_all_poll_files(self) -> None:
        self._close_all_poll_files("aborted")

    # 收尾轉態（只轉仍 poll 嘅檔——已收嘅唔郁）
    def _close_all_poll_files(self, poll_status: str) -> None:
        with self._write_lock:
            self._close_all_poll_files_locked(poll_status)

    # 收尾轉態本體（持鎖內）
    def _close_all_poll_files_locked(self, poll_status: str) -> None:
        self._close_poll_entries(poll_status, None)

    # 關閉 entry（response_index=None＝全部；只關仍 poll 嘅）
    def _close_poll_entries(self, poll_status: str, response_index: "int | None") -> None:
        poll_list = self._read_poll_list()
        current_time = timer_class.get_datetime()

        # 逐 entry：符合條件且仍 poll → 關閉
        for poll_list_entry in poll_list:
            is_response_family = poll_list_entry.get("family", "") == "response"
            entry_status = poll_list_entry.get("status", "")

            # 判定：全數收尾 → 舊記錄（冇 status）用 poll_status 補
            if response_index is None and entry_status == "":
                entry_status = poll_list_entry.get("poll_status", "")

            # 判定：指定輪次 → 家族／輪次必須相符
            if response_index is not None:
                # 判定：唔係 response 家族 → 唔郁
                if is_response_family is False:
                    continue

                # 判定：輪次唔同 → 唔郁
                if int(poll_list_entry.get("response_index", -1)) != response_index:
                    continue

            # 判定：已收（非 poll）→ 唔郁
            if entry_status != "poll":
                continue

            poll_list_entry["status"] = poll_status
            poll_list_entry["update_at"] = current_time
            poll_list_entry["finished_at"] = current_time

        self._write_poll_list(poll_list)

    # read log（讀取全文——不存在 = 空字串）
    def read(self) -> str:
        log_path = self._log_path()
        if not log_path.exists():
            return ""
        return log_path.read_text(encoding="utf-8")

    def get_summarize_thinking_content(self, tail: int = 0) -> str:
        return get_tail_content(self._summarize_thinking_path(), tail)

    def get_summarize_result_content(self, tail: int = 0) -> str:
        return get_tail_content(self._summarize_result_path(), tail)



# 模組級實例已移除——改由 context.py 的 message_log() bridging 函數取得單例

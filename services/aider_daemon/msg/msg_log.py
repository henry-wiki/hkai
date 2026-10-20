#!/usr/bin/env python3
"""
msg_log.py

msg_log 檔（messages/polling/{project_slug}/{message_id}/msg_log.txt）讀寫專屬（msg_log_class singleton——get_instance() 取得）。

外殼（與 PHP polling_log_Class 同一格式）：
    datetime（Y-m-d H:i:s 本地時區）/ source / kind / level / event / message
    metadata（有值才寫）/ payload（有值才寫）
    message_id 由目錄名承載（chat_id 由 msg_conf 帶入），不寫入 log entry

    kind：state（有轉移定義——會驅動狀態）／trace（純足跡）
"""

import json
import threading
from pathlib import Path

from ..msg.msg_main import msg_main_class
from ..utils.io import get_tail_content
from ..utils.timer import timer_class


class msg_log_class:
    """
    msg_log 檔的統一寫入/讀取（singleton——整個程序只有一個實例）。

    職責單一：msg_log 檔案的「路徑解析＋寫入（JSONL append）＋讀取」；不含任何處理流程。
    """

    _instance: "msg_log_class | None" = None
    # 串流追溯開關（預設開啟；set_stream_log() 控制）
    _stream_log_enabled: bool = True
    # 寫入鎖（callback 由多執行緒觸發——序列化寫入，避免行交錯；可重入——poll 更新整段持鎖再入寫檔）
    _write_lock = threading.RLock()

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "msg_log_class":
        """取得 msg_log_class 單例（整個程序共用同一實例）。"""
        return cls()

    def _log_path(self) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path("log", message_id)

    # 事件分類：state（有轉移定義——會驅動狀態）／trace（純足跡）
    def _get_event_kind(self, event: str) -> str:
        """查 FSM 轉移表判定事件分類（延遲 import——避免與 context 循環）。"""
        from ..msg.msg_state_machine import msg_state_machine_class
        return "state" if msg_state_machine_class.get_instance().is_status_event(event) else "trace"

    # 核心寫入（level 由呼叫端給——統一標準 JSONL append——與 PHP polling_log_Class 同一格式；回傳該行字串）
    def append_log(self, event: str, level: str, message: str, metadata: dict | None = None, payload: dict | None = None, detail: str = "", meta: dict | None = None) -> str:
        """
        外殼：datetime（Y-m-d H:i:s 本地時區）/ source / kind / level / event / message
        metadata／payload（有值才寫——ERROR 層級不寫，只進 error_log）。
        message_id 由目錄名承載（chat_id 由 msg_conf 帶入），不寫入 log entry。

        detail：error_log 專用的完整內容（空 → 沿用 message）。
        """
        log_data: dict = {
            "datetime": timer_class.get_datetime(),
            "source": "python-daemon",
            "kind": self._get_event_kind(event),
            "level": level,
            "event": event,
            "message": message,
        }

        # 判定：ERROR 層級 → 完整內容另寫 error_log；msg_log 只留一句短話（不帶 metadata／payload）
        if level == "ERROR":
            _error_record = {}
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
        # 多執行緒（主流程／摘要 worker／cache warming）→ 以鎖序列化寫入（避免行交錯）
        with self._write_lock, self._log_path().open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(log_line + "\r\n")

        return log_line

    # 語法糖：level 固定 INFO（event/message/metadata/payload 由呼叫端給）
    def append_info_log(self, event: str, message: str, metadata: dict | None = None, payload: dict | None = None, meta: dict | None = None) -> str:
        return self.append_log(event, "INFO", message, metadata, payload, meta=meta)

    # 語法糖：level 固定 SUCCESS
    def append_success_log(self, event: str, message: str, metadata: dict | None = None, payload: dict | None = None, meta: dict | None = None) -> str:
        return self.append_log(event, "SUCCESS", message, metadata, payload, meta=meta)

    # 語法糖：level 固定 ERROR（detail＝完整內容——僅寫入 error_log）
    def append_error_log(self, event: str, message: str, metadata: dict | None = None, payload: dict | None = None, detail: str = "", meta: dict | None = None) -> str:
        return self.append_log(event, "ERROR", message, metadata, payload, detail, meta)

    # 語法糖：level 固定 WARN
    def append_warn_log(self, event: str, message: str, metadata: dict | None = None, payload: dict | None = None, meta: dict | None = None) -> str:
        return self.append_log(event, "WARN", message, metadata, payload, meta=meta)

    # 設定串流追溯開關（預設開啟；set_stream_log(false) 可關閉）
    def set_stream_log(self, enabled: bool) -> None:
        self._stream_log_enabled = enabled

    def _stream_log_path(self) -> Path:
        """stream_log 檔路徑（與 msg_log 同目錄）。"""
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path("stream_log", message_id)

    # 串流追溯寫入（每個 callback 觸發寫一行 JSONL——datetime 置於最前）
    def append_stream_log(self, record: dict) -> None:
        """寫入一行 stream_log JSONL 記錄（datetime 置前——main information）。"""
        if not self._stream_log_enabled:
            return
        # datetime 置於最前（record 其餘鍵依序接續）
        output_record = {"datetime": timer_class.get_datetime()}
        output_record.update(record)
        # newline="" 避免 text mode 把 \r\n 翻譯成 \r\r\n（導致 splitlines 誤判行界）
        # 多執行緒（主流程／摘要 worker／cache warming）→ 以鎖序列化寫入（避免行交錯）
        with self._write_lock, self._stream_log_path().open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(json.dumps(output_record, ensure_ascii=False, default=str) + "\r\n")

    def _error_log_path(self) -> Path:
        """error_log 檔路徑（僅 ERROR 事件——與 stream_log 同目錄）。"""
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path("error_log", message_id)

    # error_log 寫入（僅 ERROR 事件——append JSONL）
    def append_error_log_record(self, record: dict) -> None:
        """寫入一行 error_log JSONL（錯誤事件）。"""
        with self._write_lock, self._error_log_path().open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(json.dumps(record, ensure_ascii=False, default=str) + "\r\n")

    # response thinking / answer txt 路徑（索引制——與 stream_log 同目錄）
    def _response_thinking_path(self, response_index: int) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path(f"response.{response_index}.thinking", message_id)

    def _response_result_path(self, response_index: int) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path(f"response.{response_index}.result", message_id)

    # response thinking 純文字 append（reasoning_content——帶 response index）
    def append_response_thinking(self, response_index: int, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        response_thinking_path = self._response_thinking_path(response_index)
        with response_thinking_path.open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(text)
        self._update_poll_list(response_thinking_path)

    # response result 純文字 append（content——帶 response index）
    def append_response_result(self, response_index: int, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        response_result_path = self._response_result_path(response_index)
        with response_result_path.open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(text)
        self._update_poll_list(response_result_path)

    # response replay 路徑（引擎 load 重播——獨立歸檔）
    def _response_replay_path(self, response_index: int) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path(f"response.{response_index}.replay", message_id)

    # response replay 純文字 append（引擎重播內容——帶 response index）
    # 刻意唔入 poll（_update_poll_list）：重播唔屬本輪回應——前端唔會拉、唔會顯示；此檔＝後端紀錄
    def append_response_replay(self, response_index: int, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        response_replay_path = self._response_replay_path(response_index)
        with response_replay_path.open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(text)

    # summarize thinking 路徑
    def _summarize_thinking_path(self) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path("summarize.0.thinking", message_id)

    # summarize result 路徑
    def _summarize_result_path(self) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path("summarize.0.result", message_id)

    # summarize thinking 純文字 append（reasoning_content）
    def append_summarize_thinking(self, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        summarize_thinking_path = self._summarize_thinking_path()
        with summarize_thinking_path.open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(text)
        self._update_poll_list(summarize_thinking_path)

    # summarize result 純文字 append（content）
    def append_summarize_result(self, text: str) -> None:
        if not self._stream_log_enabled:
            return
        if not text:
            return
        summarize_result_path = self._summarize_result_path()
        with summarize_result_path.open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(text)
        self._update_poll_list(summarize_result_path)

    #---------------------------------------------------------
    # poll 對照表（msg_poll_list.jsonl——Python 獨家寫；PHP 純讀）
    # 1. 路徑：msg_poll_list.jsonl（與 PHP 同一檔名規則）
    # 2. 逐 append 更新：本檔轉 poll（update 時間＋byte 長）；其餘 poll 舊檔轉 completed
    # 3. 收尾轉態：全數仍 poll → completed／aborted
    #---------------------------------------------------------

    # poll 對照表路徑
    def _get_poll_list_path(self) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path("poll_list", message_id)

    # 讀 poll 對照表（逐行解析；舊制行——冇 poll_file——唔收）
    def _read_poll_list(self) -> list:
        poll_list_path = self._get_poll_list_path()
        if not poll_list_path.exists():
            return []
        poll_list = []
        for poll_list_line in poll_list_path.read_text(encoding="utf-8").splitlines():
            # 判定：空行 → 跳過
            if poll_list_line.strip() == "":
                continue
            poll_list_entry = json.loads(poll_list_line)
            # 判定：舊制行（冇 poll_file——PHP 時代遺留）→ 唔收
            if "poll_file" not in poll_list_entry:
                continue
            poll_list.append(poll_list_entry)
        return poll_list

    # 寫 poll 對照表（整檔重寫——JSONL；LF）
    def _write_poll_list(self, poll_list: list) -> None:
        poll_list_lines = []
        for poll_list_entry in poll_list:
            poll_list_lines.append(json.dumps(poll_list_entry, ensure_ascii=False))
        with self._write_lock, self._get_poll_list_path().open("w", encoding="utf-8", newline="") as output_file:
            output_file.write("\n".join(poll_list_lines) + "\n")

    # 對照更新（一檔 append 完）：本檔起 poll（已收者唔回退）；其他仍 poll 嘅舊檔轉 completed（同一刻只有一檔寫緊）
    def _update_poll_list(self, poll_file_path: Path) -> None:
        with self._write_lock:
            self._update_poll_list_locked(poll_file_path)

    # 對照更新本體（持鎖內——讀改寫一氣呵成）
    def _update_poll_list_locked(self, poll_file_path: Path) -> None:
        poll_list = self._read_poll_list()
        current_time = timer_class.get_datetime()
        file_content_length = poll_file_path.stat().st_size

        current_entry = None

        # 逐 entry：本檔取返；其餘 poll 舊檔收 completed
        for poll_list_entry in poll_list:
            # 判定：本檔 → 記低（下面更新）
            if poll_list_entry["poll_file"] == poll_file_path.name:
                current_entry = poll_list_entry
                continue
            # 判定：其他仍在寫（poll）→ 上一隻已收——轉 completed
            if poll_list_entry["poll_status"] == "poll":
                poll_list_entry["poll_status"] = "completed"
                poll_list_entry["poll_update_at"] = current_time

        # 判定：本檔首次出現 → 起 entry（起始時間＝當下）
        if current_entry is None:
            current_entry = {
                "poll_file":           poll_file_path.name,
                "poll_status":         "poll",
                "poll_start_at":       current_time,
                "poll_update_at":      current_time,
                "poll_content_length": file_content_length,
            }
            poll_list.append(current_entry)

        # 本檔＝寫緊（poll）——update 時間＋byte 長（每 chunk 同步）
        # 狀態唔重設：新條目已起 poll（上面）；已收（completed／aborted）者唔回退——
        # 收尾後嘅遲到寫入（teardown flush）唔可以打返 poll
        current_entry["poll_update_at"] = current_time
        current_entry["poll_content_length"] = file_content_length

        self._write_poll_list(poll_list)

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
        poll_list = self._read_poll_list()
        current_time = timer_class.get_datetime()

        # 逐 entry：poll 中 → 轉目標狀態
        for poll_list_entry in poll_list:
            # 判定：唔係 poll（已收）→ 唔郁
            if poll_list_entry["poll_status"] != "poll":
                continue
            poll_list_entry["poll_status"] = poll_status
            poll_list_entry["poll_update_at"] = current_time

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



# 模組級實例已移除——改由 context.py 的 msg_log() bridging 函數取得單例

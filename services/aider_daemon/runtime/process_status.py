#!/usr/bin/env python3
"""
process_status.py

程序狀態（自我心跳）——每個程序每秒覆寫自己的狀態檔。

	檔案存在 ＋ 心跳新鮮 → 程序在跑；心跳停了（或檔案被清掉）→ 程序死了。

	檔案：{process_status_dir}/{name}.{pid}.json
	      目錄出自靜態 SSOT sc_config/hkai/hkai_config.json 的 folders.process_status（storage_dir 拼接）；
	      由 daemon_manager._apply_static_configs 展開為 process_status_dir。

	寫入者：永遠只有「該程序自己」（worker 代寫 aider 子程序——它是監護人）；
	        每程序一檔 → 無寫入競爭（同 runtime/process_list.py 的單一寫者原則）。

	內容：pid／name／program／started_at／started_at_fmt／updated_at／updated_at_fmt／beat_count
	      （只有這些——單一程序自身的資訊；ppid／來源 chat／message 走台帳，不在狀態檔重複）

	生命週期：
		register()  登記本程序負責心跳的項目（自己，或代寫的子程序）
		beat_all()  每秒呼叫一次（覆寫全部登記項——不增長）
		clear_all() 程序結束刪檔（atexit 自動註冊——正常結束不留檔；被強殺則留給對帳）
"""

import atexit
import json
import os
import time
from datetime import datetime
from pathlib import Path

# 時間格式（與 daemon 的 main_started_at_fmt 一致——同一套呈現）
TIME_FORMAT = "%Y-%m-%d %H:%M:%S"


class process_status_class:
	"""程序狀態心跳（singleton——整個程序共用同一實例）。"""

	_instance: "process_status_class | None" = None

	@classmethod
	def get_instance(cls) -> "process_status_class":
		"""取得單例（整個程序共用——登記項與心跳計數集中一處）。"""
		if cls._instance is None:
			cls._instance = cls()
		return cls._instance

	def __init__(self) -> None:
		# 狀態目錄（initialize 注入）
		self._status_dir = None
		# 登記項（key = "name.pid" → entry dict——本程序負責寫入的全部項目）
		self._entries = {}
		# 程序結束 → 刪自己的檔（被強殺不會執行——由檢視端／對帳處理）
		atexit.register(self.clear_all)

	# 注入狀態目錄（呼叫端：daemon／worker／bridge 啟動時——值來自 runtime spec）
	def initialize(self, status_dir) -> "process_status_class":
		"""注入狀態目錄（storage_dir ＋ folders.process_status）。"""
		self._status_dir = Path(status_dir)
		return self

	# 登記心跳項目（自己 ＋ 代寫的子程序——重複呼叫安全）
	def register(self, name: str, pid: int, program: str) -> None:
		"""登記一個由本程序負責心跳的項目（例：worker 登記自己 ＋ 它拉起的 aider）。"""
		pid = int(pid)

		# 判定：pid 無效 → 不登記（無從辨識）
		if pid < 1:
			return

		entry_key = name + "." + str(pid)

		# 判定：已登記 → 只更新程式名後返回（保留 started_at 與 beat_count）
		if entry_key in self._entries:
			self._entries[entry_key]["program"] = program
			return

		self._entries[entry_key] = {
			"name":       name,
			"pid":        pid,
			"program":    program,
			"started_at": time.time(),
			"beat_count": 0,
		}

	# 心跳一次（每秒呼叫——覆寫全部登記項）
	def beat_all(self) -> None:
		"""把全部登記項各寫一次（檔案覆寫——不增長）。"""
		# 判定：未注入目錄 → 不動作（初始化失敗不影響主流程）
		if self._status_dir is None:
			return

		for entry_key in list(self._entries.keys()):
			self._write_entry(self._entries[entry_key])

	# 刪除全部登記項的狀態檔（程序結束——atexit 呼叫；重複呼叫安全）
	def clear_all(self) -> None:
		"""刪除本程序負責的全部狀態檔（程序正常結束——不留殘檔）。"""
		# 判定：未注入目錄 → 不動作
		if self._status_dir is None:
			return

		for entry_key in list(self._entries.keys()):
			status_path = self._get_status_path(self._entries[entry_key])
			try:
				if status_path.exists():
					status_path.unlink()
			except OSError:
				pass
		self._entries = {}

	# 取消登記並刪除該項狀態檔（子程序結束——由監護人呼叫，如 worker 收掉 aider）
	def unregister(self, name: str, pid: int) -> None:
		"""取消登記（刪除該項狀態檔——程序已結束，不留殘檔）。"""
		entry = self._entries.pop(name + "." + str(int(pid)), None)

		# 判定：未登記 → 無事可做
		if entry is None:
			return

		status_path = self._get_status_path(entry)
		try:
			if status_path.exists():
				status_path.unlink()
		except OSError:
			pass

	# 清除過期狀態檔（對帳——被強殺的程序不會刪自己的檔；daemon 啟動時掃一次）
	def sweep_stale(self, max_age_sec: int = 60) -> int:
		"""刪除心跳過期的狀態檔，回傳刪除數（max_age_sec＝多久沒心跳視為死）。"""
		# 判定：未注入目錄或目錄不存在 → 無事可做
		if self._status_dir is None or not self._status_dir.exists():
			return 0

		removed_count = 0
		for status_path in self._status_dir.glob("*.json"):
			try:
				if (time.time() - status_path.stat().st_mtime) > max_age_sec:
					status_path.unlink()
					removed_count += 1
			except OSError:
				pass

		return removed_count

	# ------------------------------------------------------------------ 內部

	# 寫入單一登記項（每秒一次——先建目錄，失敗只忽略）
	def _write_entry(self, entry: dict) -> None:
		# 判定：未注入目錄 → 不動作
		if self._status_dir is None:
			return

		status_path = self._get_status_path(entry)

		try:
			self._status_dir.mkdir(parents=True, exist_ok=True)
			entry["beat_count"] += 1

			now_time = datetime.now()
			record = {
				"pid":             entry["pid"],
				"name":            entry["name"],
				"program":         entry["program"],
				"started_at":      int(entry["started_at"]),
				"started_at_fmt":  datetime.fromtimestamp(entry["started_at"]).strftime(TIME_FORMAT),
				"updated_at":      int(now_time.timestamp()),
				"updated_at_fmt":  now_time.strftime(TIME_FORMAT),
				"beat_count":      entry["beat_count"],
			}

			# 覆寫（每程序一檔——只有自己會寫，無競爭；格式化輸出＋原子替換，避免讀到半截）
			content = json.dumps(record, indent=2, ensure_ascii=False)
			tmp_path = Path(str(status_path) + ".tmp")
			tmp_path.write_text(content, encoding="utf-8", newline="\n")
			os.replace(tmp_path, status_path)
		except Exception:  # noqa: BLE001
			pass  # 狀態檔寫入失敗絕不影響主流程

	# 狀態檔路徑（{name}.{pid}.json）
	def _get_status_path(self, entry: dict) -> Path:
		status_dir = self._status_dir if self._status_dir is not None else Path()
		return status_dir / (entry["name"] + "." + str(entry["pid"]) + ".json")


# ===== bridging（模組級——供 daemon_manager／worker／msg_processor 直接呼叫；同 sc_logger 的寫法）=====
# 注入狀態目錄（daemon／worker 啟動時——值來自 runtime spec）
def initialize(status_dir) -> "process_status_class":
	return process_status_class.get_instance().initialize(status_dir)


# 登記心跳項目（自己 ＋ 代寫的子程序）
def register(name: str, pid: int, program: str) -> None:
	return process_status_class.get_instance().register(name, pid, program)


# 取消登記（刪除該項狀態檔——程序已結束）
def unregister(name: str, pid: int) -> None:
	return process_status_class.get_instance().unregister(name, pid)


# 心跳一次（每秒呼叫——覆寫全部登記項）
def beat_all() -> None:
	return process_status_class.get_instance().beat_all()


# 刪除本程序全部狀態檔（程序結束）
def clear_all() -> None:
	return process_status_class.get_instance().clear_all()


# 清除過期狀態檔（對帳；回傳刪除數）
def sweep_stale(max_age_sec: int = 60) -> int:
	return process_status_class.get_instance().sweep_stale(max_age_sec)

#!/usr/bin/env python3
"""
process_status.py

程序狀態（自我心跳）——每個程序每秒覆寫自己的狀態檔。

	檔案存在 ＋ 心跳新鮮 → 程序在跑；心跳停了（或檔案被清掉）→ 程序死了。

	檔案：{PROCESS_DIR}/{name}.{pid}.json
	      目錄＝context.PROCESS_DIR（站台根 data/process_status——核心路徑，唔靠 runtime.json）；
	      與 aider_daemon 的同名模組同格式（兩套件各自持有自己的 runtime 基礎設施）。

	寫入者：永遠只有「該程序自己」（worker 代寫 aider 子程序——它是監護人）；
	        每程序一檔 → 無寫入競爭（同 runtime/process_list.py 的單一寫者原則）。

	內容：pid／name／program／started_at／started_at_fmt／updated_at／updated_at_fmt／beat_count
	      （只有這些——單一程序自身的資訊；ppid／來源 chat／message 走台帳，不在狀態檔重複）

	生命週期：
		register()  登記本程序負責心跳的項目（自己，或代寫的子程序）
		beat()  每秒呼叫一次（覆寫全部登記項——不增長）
		clear() 程序結束刪檔（atexit 自動註冊——正常結束不留檔；被強殺則留給對帳）
"""

import atexit
from pathlib import Path

from ..context import PROCESS_DIR, PROGRAM_LABEL, PROGRAM_NAME, get_timestamp, strtotime
from ..utils.io import write_json_atomic


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
		# 登記項（key = pid → process_entry——即寫入檔案嘅同一份內容）
		self._process_entries = {}
		# 程序結束 → 刪自己的檔（被強殺不會執行——由檢視端／對帳處理）
		atexit.register(self.clear)

	# 登記心跳項目（自己 ＋ 代寫的子程序——重複呼叫安全）
	def register(self, pid: int) -> None:
		"""登記（重複安全——已登記即返回）。"""
		pid = int(pid)

		# 判定：pid 無效 → 不登記（無從辨識）
		if pid < 1:
			return

		# 判定：已登記 → 無事可做
		if pid in self._process_entries:
			return

		# 建立登記項＋狀態檔（唯一入口）
		self.create(pid)


	# 心跳一次（每秒呼叫——覆寫全部登記項）
	def beat(self) -> None:
		"""把全部登記項各寫一次（檔案覆寫——不增長）。"""
		for pid in list(self._process_entries.keys()):
			self.update(pid)

	# 刪除全部登記項的狀態檔（程序結束——atexit 呼叫；重複呼叫安全）
	def clear(self) -> None:
		"""刪除本程序負責的全部狀態檔（程序正常結束——不留殘檔）。"""
		for pid in list(self._process_entries.keys()):
			self.unregister(pid)

	# 取消登記並刪除該項狀態檔（子程序結束——由監護人呼叫，如 worker 收掉 aider）
	def unregister(self, pid: int) -> None:
		"""取消登記（按 pid 移除登記項，刪檔——程序已結束，不留殘檔）。"""
		pid = int(pid)
		entry = self._process_entries.pop(pid, None)

		# 判定：未登記 → 無事可做
		if entry is None:
			return

		act_process_file = self._get_process_file(pid)
		try:
			if act_process_file.exists():
				act_process_file.unlink()
		except OSError:
			pass

	# ------------------------------------------------------------------ 內部

	# 寫入單一登記項（每秒一次——目錄由 write_json_atomic 建立；失敗只忽略）
	def update(self, pid: int) -> None:
		process_entry = self._process_entries.get(int(pid))

		# 判定：未登記 → 無事可做
		if process_entry is None:
			return

		process_entry["beat_count"] += 1

		updated_at = get_timestamp()
		process_entry["updated_at"]     = updated_at
		process_entry["updated_at_fmt"] = strtotime(updated_at)

		# 原子寫入（tmp → replace；失敗由 write_json_atomic 記 log）
		write_json_atomic(self._get_process_file(pid), process_entry)


	def create(self, pid: int) -> None:
		started_at = get_timestamp()
		started_at_fmt = strtotime(started_at)

		# 登記項（唯一一份——即係寫入檔案嘅內容）
		process_entry = {
			"pid":            pid,
			"name":           PROGRAM_NAME,
			"program":        PROGRAM_LABEL,
			"started_at":     started_at,
			"started_at_fmt": started_at_fmt,
			"updated_at":     started_at,
			"updated_at_fmt": started_at_fmt,
			"beat_count":     0,
		}

		self._process_entries[pid] = process_entry

		# 原子寫入（tmp → replace；失敗由 write_json_atomic 記 log）
		write_json_atomic(self._get_process_file(pid), process_entry)


	# 狀態檔路徑（PROGRAM_NAME.<pid>.json）
	def _get_process_file(self, pid: int) -> Path:
		return Path(PROCESS_DIR) / (PROGRAM_NAME + "." + str(int(pid)) + ".json")


# ===== bridging（模組級——供 daemon_manager／worker／msg_processor 直接呼叫；同 sc_logger 的寫法）=====
# 登記心跳項目（name／program 由 context 決定；只需 pid）
def register(pid: int) -> None:
	return process_status_class.get_instance().register(pid)


# 取消登記（按 pid 刪除狀態檔——程序已結束）
def unregister(pid: int) -> None:
	return process_status_class.get_instance().unregister(pid)


# 心跳一次（每秒呼叫——覆寫全部登記項）
def beat() -> None:
	return process_status_class.get_instance().beat()


# 刪除本程序全部狀態檔（程序結束）
def clear() -> None:
	return process_status_class.get_instance().clear()

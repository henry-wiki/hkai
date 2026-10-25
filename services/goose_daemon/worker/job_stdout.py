#!/usr/bin/env python3
"""
job_stdout.py

job_stdout 檔讀寫專屬（job_stdout_class singleton——get_instance() 取得）。

內容：stdout 原樣輸出（純紀錄——原 msg_response）。
"""

from pathlib import Path

from ..context import message_store
from ..msg import message_context


class job_stdout_class:
    """
    job_stdout 檔的統一讀取/寫入（singleton——整個程序只有一個實例）。

    職責單一：job_stdout 檔案的「路徑＋讀取」；不含任何處理流程。
    """

    _instance: "job_stdout_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "job_stdout_class":
        """取得 job_stdout_class 單例（整個程序共用同一實例）。"""
        return cls()

    def _stdout_path(self) -> Path:
        message_dir = message_store().get_message_dir()
        return message_context.get_msg_file_path(message_dir, "stdout")

    # 有檔＝已開始輸出（防重訊號）
    def exists(self) -> bool:
        return self._stdout_path().exists()

    # 讀全文（無檔＝空字串）
    def read(self) -> str:
        stdout_path = self._stdout_path()
        if not stdout_path.exists():
            return ""
        return stdout_path.read_text(encoding="utf-8")

# 模組級實例已移除——改由 context.py 的 job_stdout() bridging 函數取得單例

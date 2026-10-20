#!/usr/bin/env python3
"""
msg_claim.py

msg_claim 檔讀寫專屬（msg_claim_class singleton——get_instance() 取得）。

內容：處理認領（worker_pid＋認領時刻——原子建立；同時只一個 worker 認領成功）。
"""

import json
import os
import time
from pathlib import Path

import psutil

from ..msg.msg_main import msg_main_class


class msg_claim_class:
    """
    msg_claim 檔的統一認領/讀取（singleton——整個程序只有一個實例）。

    職責單一：msg_claim 檔案的「路徑＋原子認領＋讀主人 pid」；不含任何處理流程。
    """

    _instance: "msg_claim_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "msg_claim_class":
        """取得 msg_claim_class 單例（整個程序共用同一實例）。"""
        return cls()

    def _claim_path(self) -> Path:
        message_id = msg_main_class.get_instance().get_message_id()
        return msg_main_class.get_instance().get_msg_file_path("claim", message_id)

    # 原子認領（成功＝True；活主人＝False；死主人＝接管後 True）
    def claim(self, worker_pid: int) -> bool:
        # 首次原子認領（O_EXCL——同時只一個贏）
        if self._create_claim_file(worker_pid) is True:
            return True

        # 已有主人——活住＝唔爭
        if self.is_owner_alive() is True:
            return False

        # 死主人＝殘檔——刪走後再認領（接管）
        self._claim_path().unlink(missing_ok=True)

        return self._create_claim_file(worker_pid)

    # 原子建立認領檔（已存在＝False）
    def _create_claim_file(self, worker_pid: int) -> bool:
        try:
            file_descriptor = os.open(self._claim_path(), os.O_CREAT | os.O_EXCL | os.O_WRONLY)
        except FileExistsError:
            return False

        claim_data = {}
        claim_data["worker_pid"] = worker_pid
        claim_data["claimed_at"] = time.strftime("%Y-%m-%d %H:%M:%S")

        with os.fdopen(file_descriptor, "w", encoding="utf-8") as claim_file:
            json.dump(claim_data, claim_file, ensure_ascii=False)

        return True

    # 現任主人 pid（無檔／壞檔 → 0）
    def get_owner_pid(self) -> int:
        claim_path = self._claim_path()

        # 判定：無認領檔 → 0
        if not claim_path.exists():
            return 0

        try:
            claim_data = json.loads(claim_path.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return 0

        # 判定：壞檔 → 0
        if not isinstance(claim_data, dict):
            return 0

        return int(claim_data.get("worker_pid", 0))

    # 主人仲生唔生（無主／死人 → False）
    def is_owner_alive(self) -> bool:
        owner_pid = self.get_owner_pid()

        # 判定：無主 → False
        if owner_pid <= 0:
            return False

        return psutil.pid_exists(owner_pid)


# 模組級實例已移除——改由 context.py 的 msg_claim() bridging 函數取得單例

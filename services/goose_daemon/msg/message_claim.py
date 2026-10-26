#!/usr/bin/env python3
"""
message_claim.py

message_claim 檔讀寫專屬（message_claim_class singleton——get_instance() 取得）。

內容：處理認領（worker_pid＋認領時刻——原子建立；同時只一個 worker 認領成功）。
"""

import json
import time

import psutil

from ..context import message_store
from ..utils import io


class message_claim_class:
    """
    message_claim 檔的統一認領/讀取（singleton——整個程序只有一個實例）。

    職責單一：message_claim 檔案的「路徑＋原子認領＋讀主人 pid」；不含任何處理流程。
    """

    _instance: "message_claim_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "message_claim_class":
        """取得 message_claim_class 單例（整個程序共用同一實例）。"""
        return cls()

    # 原子認領（成功＝True；活主人＝False；死主人＝接管後 True）
    def claim(self, worker_pid: int) -> bool:
        # 首次原子認領（O_EXCL——同時只一個贏）
        if self._create_claim_file(worker_pid) is True:
            return self._verify_owner(worker_pid)

        # 已有主人——活住＝唔爭
        if self.is_owner_alive() is True:
            return False

        # 死主人＝殘檔——刪走後再認領（接管）
        message_store().get_claim_path().unlink(missing_ok=True)

        # 判定：接管建檔失敗（另一個人搶先）→ 放棄
        if self._create_claim_file(worker_pid) is False:
            return False

        return self._verify_owner(worker_pid)

    # 認領後回讀驗證（防接管 race：兩個同時刪殘檔再建——最後寫入者才贏）
    def _verify_owner(self, worker_pid: int) -> bool:
        owner_pid = self.get_owner_pid()

        return owner_pid == worker_pid

    # 原子建立認領檔（已存在＝False——open "x" 內部用 O_EXCL／CREATE_NEW）
    def _create_claim_file(self, worker_pid: int) -> bool:
        claim_data = {}
        claim_data["worker_pid"] = worker_pid
        claim_data["claimed_at"] = time.strftime("%Y-%m-%d %H:%M:%S")

        try:
            with message_store().get_claim_path().open("x", encoding="utf-8") as claim_file:
                json.dump(claim_data, claim_file, ensure_ascii=False)
        except FileExistsError:
            return False

        return True

    # 現任主人 pid（無檔／壞檔 → 0）
    def get_owner_pid(self) -> int:
        return self._read_claim_owner_pid()

    # 主人仲生唔生（無主／死人 → False）
    def is_owner_alive(self) -> bool:
        owner_pid = self._read_claim_owner_pid()

        # 判定：無主（無檔／壞檔）→ False
        if owner_pid <= 0:
            return False

        try:
            _ = psutil.Process(owner_pid)
        except psutil.Error:
            return False

        return True

    # 讀當前工單主人 pid（無檔／壞檔 → 0）
    def _read_claim_owner_pid(self) -> int:
        claim_path = message_store().get_claim_path()

        # 判定：無認領檔 → 0
        if not claim_path.exists():
            return 0

        claim_data = io.read_json(claim_path)

        # 判定：讀唔到／壞檔 → 0
        if claim_data is None:
            return 0

        return int(str(claim_data.get("worker_pid", 0)))


# 模組級實例已移除——改由 context.py 的 message_claim() bridging 函數取得單例

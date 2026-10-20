#!/usr/bin/env python3
"""
process_helper_class.py

子程序安全終止工具（process_helper_class singleton——get_instance() 取得）。

核心邏輯：優雅關閉（Graceful Shutdown）轉強制清理——
    terminate（SIGTERM）→ join(timeout) → 仍未停 → kill（SIGKILL）→ 補一次無 timeout join
    （確保完全回收作業系統 PID 資源——防殭屍程序）。
"""

import logging
import multiprocessing


class process_helper_class:
    """
    子程序安全終止（singleton——整個程序只有一個實例；無狀態——統一入口）。

    職責單一：子程序的安全終止（terminate → kill 兩段式）；不含任何處理流程。
    """

    _instance: "process_helper_class | None" = None
    # 目前 message 語境（型別明確——類別屬性 = 初始 None——singleton 重用不重設；setter 建立實例屬性遮蔽）
    _daemon_conf: dict | None = None
    _chat_id: str | int | None = None
    _message_id: str | int | None = None

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "process_helper_class":
        """取得 process_helper_class 單例（整個程序共用同一實例）。"""
        return cls()

    # 設定目前 daemon 語境（daemon_conf——之後 safe_terminate 不需再傳）
    def set_daemon_conf(self, daemon_conf: dict) -> None:
        self._daemon_conf = daemon_conf

    # 設定目前 message 語境（chat_id——之後 safe_terminate 不需再傳）
    def set_chat_id(self, chat_id: str | int) -> None:
        self._chat_id = chat_id

    # 設定目前 message 語境（message_id——之後 safe_terminate 不需再傳）
    def set_message_id(self, message_id: str | int) -> None:
        self._message_id = message_id

    # 安全終止子程序：優先 terminate（優雅）→ 超時仍活 → kill（強制）→ 補 join（回收 PID——防殭屍）
    def safe_terminate(self, proc: multiprocessing.Process, timeout: float = 5.0) -> None:
        """終止子程序：無效/已終止直接返回；terminate 超時則強制 kill。"""
        # 無效或已終止 → 直接返回（無需處理）
        if proc is None or not proc.is_alive():
            return

        proc.terminate()
        proc.join(timeout=timeout)

        # 超時仍未停止 → 強制 kill（再補一次無 timeout join——確保完全回收 OS PID 資源）
        if proc.is_alive():
            logging.getLogger(__name__).warning(f"Process {proc.pid} didn't stop in {timeout}s, killing force-fully.")
            proc.kill()
            proc.join()


# 模組級實例已移除——改由 context.py 的 process_helper() bridging 函數取得單例

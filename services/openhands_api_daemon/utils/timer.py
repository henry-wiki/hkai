#!/usr/bin/env python3
"""
timer.py

    統一時間格式輸出（timer_class singleton）。
"""

from datetime import datetime, timezone


class timer_class:
    """
    時間格式化工具（singleton——整個程序共用）。
    """

    _instance: "timer_class | None" = None
    # 目前 message 語境（型別明確——類別屬性 = 初始 None——singleton 重用不重設；setter 建立實例屬性遮蔽）
    _daemon_conf: dict | None = None
    _chat_id: str | int | None = None
    _message_id: str | int | None = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "timer_class":
        """取得 timer_class 單例。"""
        return cls()

    # 設定目前 daemon 語境（daemon_conf——之後 get_datetime 不需再傳）
    def set_daemon_conf(self, daemon_conf: dict) -> None:
        self._daemon_conf = daemon_conf

    # 設定目前 message 語境（chat_id——之後 get_datetime 不需再傳）
    def set_chat_id(self, chat_id: str | int) -> None:
        self._chat_id = chat_id

    # 設定目前 message 語境（message_id——之後 get_datetime 不需再傳）
    def set_message_id(self, message_id: str | int) -> None:
        self._message_id = message_id

    @staticmethod
    def get_datetime() -> str:
        """回傳秒精度時間字串（Y-m-d H:M:S）。"""
        return datetime.now(timezone.utc).astimezone().strftime("%Y-%m-%d %H:%M:%S")

    @staticmethod
    def get_datetime_ms() -> str:
        """回傳毫秒精度時間字串（Y-m-d H:M:S.0000——後4位）。"""
        return datetime.now(timezone.utc).astimezone().strftime("%Y-%m-%d %H:%M:%S.%f")[:-4]

    @staticmethod
    def strtotime(timestamp: int) -> str:
        """epoch 秒 → Y-m-d H:i:s 格式（PHP date() 同格式）。"""
        return datetime.fromtimestamp(timestamp).strftime("%Y-%m-%d %H:%M:%S")

#!/usr/bin/env python3
"""
auth_setup.py

API keys 載入器（oauth keys 檔 → 環境變數）。

必須在 import aider 前執行。
"""

import os
from pathlib import Path

from dotenv import dotenv_values


class api_key_loader_class:
    """
    API keys 載入器（singleton——get_instance() 取得）。

    職責單一：讀取 oauth keys 檔 → 寫入環境變數（必須在 import aider 前執行）。
    """

    _instance: "api_key_loader_class | None" = None
    _loaded_keys: list

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "api_key_loader_class":
        """取得 api_key_loader_class 單例（整個程序共用同一實例）。"""
        return cls()

    def load(self, path: Path) -> None:
        """
        讀取 API keys 並寫入環境變數。

        必須在 import aider 前執行。
        解析交給 python-dotenv（aider／litellm 同一支——標準、穩定）；
        本檔只做兩件事：篩出 <NAME>_API_KEY、寫入環境變數。

        檔內格式（載 <NAME>_API_KEY 入環境變數；其他私隱設定〔如機器路徑〕可留檔內——原樣跳過）：

            NAME_API_KEY=value
            NAME_API_KEY="value"
            NAME_API_KEY='value'
            # comment
        """
        self._loaded_keys = []

        # 解析 env 檔（python-dotenv——quote／空格／跳脫等邊界交標準庫；不做插值）
        values = dotenv_values(path, interpolate=False)

        for key, value in values.items():
            # 只載 <NAME>_API_KEY 格式（非 key 私隱設定——原樣跳過）
            if not key.endswith("_API_KEY"):
                continue

            # 裸 KEY（無值）→ 跳過
            if value is None:
                continue

            # setdefault：僅在未設定時寫入（不覆蓋外部已注入的環境變數）
            os.environ.setdefault(key, value)
            self._loaded_keys.append(key)

    def reload(self, path) -> None:
        """
        重新載入 API keys（每條新問題前呼叫——檔案為唯一真相）。

        規則：
        1. 檔案內有值 → 覆寫環境變數（唔用 setdefault，改動即時生效）
        2. 檔案內清空（""）或消失 → 由環境變數移除（唔留記憶體舊值）
        """
        values = dotenv_values(path, interpolate=False)

        # 先移走上一次載入、而今次已清空／消失嘅 key
        for loaded_key in getattr(self, "_loaded_keys", []):
            if values.get(loaded_key) in (None, ""):
                os.environ.pop(loaded_key, None)

        self._loaded_keys = []

        # 逐條寫入（覆寫）
        for key, value in values.items():
            # 只載 <NAME>_API_KEY 格式
            if not key.endswith("_API_KEY"):
                continue

            # 清空／裸 KEY → 由環境變數移除
            if value is None or value == "":
                os.environ.pop(key, None)
                continue

            os.environ[key] = value
            self._loaded_keys.append(key)

    def get_loaded_key_names(self) -> list:
        """回傳本次 load() 成功載入的 key 名列表（不含 value）。"""
        return list(self._loaded_keys)

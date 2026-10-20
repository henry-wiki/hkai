#!/usr/bin/env python3
"""
auth_setup.py

API keys 載入器（oauth keys 檔 → 環境變數——goose 子程序亦由此取 key）。

必須在啟動 goose 前執行。
"""

import os
from pathlib import Path

from dotenv import dotenv_values


class api_key_loader_class:
    """
    API keys 載入器（singleton——get_instance() 取得）。

    職責單一：讀取 oauth keys 檔 → 寫入環境變數（必須在啟動 goose 前執行）。
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

        必須在啟動 goose 前執行。
        解析交給 python-dotenv（標準解析器——quote／空格／跳脫等邊界交佢）；
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

    def get_loaded_key_names(self) -> list:
        """回傳本次 load() 成功載入的 key 名列表（不含 value）。"""
        return list(self._loaded_keys)

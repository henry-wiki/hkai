#!/usr/bin/env python3
"""
db_connection.py

DB 連線政策唯一來源（唯讀／逾時／交易／錯誤形狀）——子類只寫 SQL 呼叫，唔自己 connect。

規則：**唔 cache 連線**（每個方法開／關一次）——避免長期持有 WAL 快照。
"""

import sqlite3
from contextlib import contextmanager
from pathlib import Path


class db_connection_class:
    """DB 存取基底（無狀態——路徑＋唯讀旗標；連線即用即關）。"""

    def __init__(self, db_file: Path, read_only: bool):
        self._db_file = db_file
        self._read_only = read_only

    def _connect(self):
        """開一條連線；DB 檔唔存在＝None（唯讀類上層當冇資料）。"""
        # 判定：檔案唔存在 → None（新環境／未建立）
        if self._db_file.is_file() is False:
            return None

        # 判定：唯讀 → mode=ro（永不寫）
        if self._read_only is True:
            return sqlite3.connect("file:" + self._db_file.as_posix() + "?mode=ro", uri=True)

        connection = sqlite3.connect(str(self._db_file), timeout=30)
        connection.isolation_level = None
        return connection

    @contextmanager
    def connection(self):
        """借出連線（離開即關；DB 唔存在＝None）。"""
        connection = self._connect()
        try:
            yield connection
        finally:
            # 判定：有連線 → 關閉（DB 唔存在＝冇嘢關）
            if connection is not None:
                connection.close()

    def query_all(self, sql: str, params: tuple = ()) -> list:
        """查詢 → 全部列（冇 DB → 空 list）。"""
        with self.connection() as connection:
            # 判定：冇 DB → 空 list
            if connection is None:
                return []
            return connection.execute(sql, params).fetchall()

    def query_one(self, sql: str, params: tuple = ()):
        """查詢 → 第一列（冇 DB／冇列 → None）。"""
        with self.connection() as connection:
            # 判定：冇 DB → None
            if connection is None:
                return None
            return connection.execute(sql, params).fetchone()

    def execute(self, sql: str, params: tuple = ()) -> None:
        """單句寫入（自動 commit；冇 DB＝乜都唔做）。"""
        with self.connection() as connection:
            # 判定：冇 DB → 唔做
            if connection is None:
                return
            connection.execute(sql, params)

    @contextmanager
    def transaction(self):
        """寫入交易（BEGIN IMMEDIATE → COMMIT；例外 ROLLBACK）。"""
        with self.connection() as connection:
            # 判定：冇 DB → 掟錯（寫入類唔應該喺缺失 DB 上執行）
            if connection is None:
                raise RuntimeError("db file missing: " + str(self._db_file))

            connection.execute("BEGIN IMMEDIATE")
            try:
                yield connection
                connection.execute("COMMIT")
            except Exception:
                # 判定：交易仍開 → 回滾（BEGIN 失敗時冇交易，直接拋）
                if connection.in_transaction is True:
                    connection.execute("ROLLBACK")
                raise

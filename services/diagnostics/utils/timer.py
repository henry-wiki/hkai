#!/usr/bin/env python3
"""timer.py — 通用時間（無狀態；唔用 singleton）。

職責：
- get_datetime()：現在時間 → 顯示字串（detected_at 契約：YYYY-MM-DD HH:MM:SS）
- get_local_datetime()：現在本機時間 → 帶時區 datetime（需要偏移／顯示名時用）

上游：diagnostics 各偵測模組（project_env／system_env）
下游：datetime（stdlib）
"""

import datetime

# detected_at 契約格式（PHP 顯示層直接讀——改格式即改契約）
DATETIME_FORMAT = "%Y-%m-%d %H:%M:%S"


def get_datetime() -> str:
    """現在時間（YYYY-MM-DD HH:MM:SS）。"""
    return datetime.datetime.now().strftime(DATETIME_FORMAT)


def get_local_datetime() -> datetime.datetime:
    """現在本機時間（帶時區——取 UTC 偏移／時區顯示名用）。"""
    return datetime.datetime.now().astimezone()

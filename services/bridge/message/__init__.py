#!/usr/bin/env python3
"""
message 套件——訊息紙箱狀態（bridge 對內讀取層）。

    extract_progress.py     圖片抽取進度（meta.json＋attachments/*.meta.json）

對外 API：message.extract_progress(message_dir) -> dict
"""

from .extract_progress import extract_progress
from .get_msg_status import get_msg_status

__all__ = ["extract_progress", "get_msg_status"]

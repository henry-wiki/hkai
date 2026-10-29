#!/usr/bin/env python3
"""
get_msg_status.py

訊息狀態（bridge 的 message state provider——純讀）。

職責：
- get_msg_status(message_dir)：讀 <箱>/msg_status.txt 最後一行（冇檔／空白 → 空字串）

上游：bridge server_stream._event_generator（每拍）
下游：檔案系統（msg_status.txt）
"""

from pathlib import Path


def get_msg_status(message_dir: Path) -> str:
    """讀 msg_status.txt 最後一行（冇檔／空白＝空字串）。"""
    status_path = message_dir / "msg_status.txt"

    # 判定：狀態檔唔存在 → 回空
    if status_path.is_file() is False:
        return ""

    try:
        status_text = status_path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return ""

    status_lines = [status_line.strip() for status_line in status_text.splitlines() if status_line.strip() != ""]

    # 判定：冇任何狀態行 → 回空
    if len(status_lines) == 0:
        return ""

    return status_lines[-1]

#!/usr/bin/env python3
"""
message_context.py

訊息檔案契約（常數單一來源）。

職責：msg_* 檔嘅命名契約——類型 → 副檔名，同組檔名／路徑。
上游：message_store.get_msg_file_path()／init_msg_paths()。
下游：無（純常數＋純函數）。

新增／修改檔案類型只改呢個檔；必須同 PHP poll_filetype 表一致。
"""

from pathlib import Path

# 預設副檔名（類型未列明時用）
DEFAULT_MSG_FILE_EXTENSION = "txt"

# type → 副檔名（與 PHP poll_filetype 一一對應）
POLLING_MSG_FILE_TYPES = {
    "conf": "json",
    "status": "txt",
    "prompt": "json",
    "stdout": "txt",                # 純紀錄（原 response）
    "response_thinking": "txt",
    "response_result": "txt",
    "log": "txt",
    "stderr": "txt",
    "claim": "json",                # 處理認領（原子）
    "permission": "json",           # 權限請求（等待交 client——PHP 讀出提示卡）
    "permission_decision": "json",  # 權限決定（client 讀——PHP 寫）
    "error": "json",
    "stream_log": "jsonl",
    "error_log": "jsonl",
    "poll_list": "jsonl",
    "turn_list": "jsonl",
    "summarize": "txt",
    "summarize_result": "txt",
    "summarize_thinking": "txt",
}


def get_msg_file_path(message_dir: Path, msg_type: str) -> Path:
    """單一 type 檔路徑：{message_dir}/msg_{type}.{ext}；conf 契約＝meta.json（與 PHP 同一規則）。"""
    # 判定：conf 類型 → 固定 meta.json（全階段同名）
    if msg_type == "conf":
        return message_dir / "meta.json"

    file_extension = DEFAULT_MSG_FILE_EXTENSION

    # 判定：查表有呢個類型 → 用表嘅副檔名
    if msg_type in POLLING_MSG_FILE_TYPES:
        file_extension = POLLING_MSG_FILE_TYPES[msg_type]

    return message_dir / f"msg_{msg_type}.{file_extension}"

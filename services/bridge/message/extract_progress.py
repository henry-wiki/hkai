#!/usr/bin/env python3
"""
extract_progress.py

紙箱圖片抽取進度（bridge 的 message state provider——純讀，唔跑抽取）。

職責：
- extract_progress(message_dir)：讀 <箱>/meta.json（attachments／status）＋
  <箱>/attachments/<檔名去副檔名>.meta.json（state／ocr.text）→ 回傳進度 dict

輸出契約（stream phase 事件直接使用）：
    {"state": overall, "done": n, "total": n, "files": [{"idx", "state", "ocr_chars"}, ...]}

overall 優先次序：cancelled → done（全 done）→ done_with_errors → done（meta.status=polling）
                → running（有 done／failed）→ pending

上游：bridge server_stream._event_generator（每拍）
下游：檔案系統（meta.json／attachments/*.meta.json）
"""

import json
from pathlib import Path
from typing import Any


def extract_progress(message_dir: Path) -> dict[str, Any]:
    """讀該訊息紙箱嘅圖片抽取進度（冇附件／讀唔到 → 空 dict）。"""
    meta_path = message_dir / "meta.json"

    # 判定：冇 meta.json → 空
    if meta_path.is_file() is False:
        return {}

    try:
        meta_data = json.loads(meta_path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}

    attachments = meta_data.get("attachments", [])

    # 判定：attachments 格式唔啱 → 空
    if isinstance(attachments, list) is False:
        return {}

    # 判定：冇附件 → 空（純文字訊息唔使報抽取進度）
    if len(attachments) == 0:
        return {}

    attachments_dir = message_dir / "attachments"
    message_status = str(meta_data.get("status", ""))

    status_list: list[dict[str, Any]] = []

    for attachment_index, attachment_item in enumerate(attachments, start=1):
        file_name = str(attachment_item.get("filename", ""))

        # 判定：冇檔名 → 下一個
        if file_name == "":
            continue

        file_stem = Path(file_name).stem
        result_path = attachments_dir / (file_stem + ".meta.json")
        file_state = "pending"
        ocr_chars = 0

        # 判定：結果檔存在 → 讀 state／OCR 字數
        if result_path.is_file() is True:
            try:
                result_data = json.loads(result_path.read_text(encoding="utf-8"))
                file_state = str(result_data.get("state", "done"))
                ocr_data = result_data.get("ocr", {})

                # 判定：ocr 係 dict → 取文字長度
                if isinstance(ocr_data, dict) is True:
                    ocr_chars = len(str(ocr_data.get("text", "")))
            except (OSError, ValueError):
                file_state = "pending"

        status_list.append({"idx": attachment_index, "state": file_state, "ocr_chars": ocr_chars})

    state_list = [status_item["state"] for status_item in status_list]
    overall_state = "pending"

    # 判定：訊息已取消 → cancelled
    if message_status == "cancelled":
        overall_state = "cancelled"
    # 判定：全部 done → done
    elif len(state_list) > 0 and all(state_item == "done" for state_item in state_list):
        overall_state = "done"
    # 判定：全部完結且有 failed → done_with_errors
    elif len(state_list) > 0 and all(state_item in ("done", "failed") for state_item in state_list) and "failed" in state_list:
        overall_state = "done_with_errors"
    # 判定：已放行（meta.status=polling）→ 抽取已完結
    elif message_status == "polling":
        overall_state = "done"
    # 判定：有 done／failed → running
    elif len([state_item for state_item in state_list if state_item in ("done", "failed")]) > 0:
        overall_state = "running"

    done_count = len([state_item for state_item in state_list if state_item == "done"])

    return {"state": overall_state, "done": done_count, "total": len(status_list), "files": status_list}

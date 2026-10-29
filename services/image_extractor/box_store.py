# pyright: reportExplicitAny=false, reportAny=false
#!/usr/bin/env python3
"""
box_store.py — 紙箱存取（一個箱＝polling|archive/<slug>/<chat_sid>/<prompt_id>/）。

職責：
- 由身份（project_slug／chat_sid／message_id）解析箱目錄（polling 先、archive 後）
- 讀箱內 meta.json
- 只改 meta.json 嘅 status（原子寫）
- 由 meta.attachments 列出待抽取圖片（idx 由 1 起）

上游：extractor（run／cancel）、bridge server_image_extract（解析箱）
下游：context（MESSAGES_DIR）、json／pathlib（stdlib）
"""

# pyright: reportExplicitAny=false, reportAny=false
# （meta.json／attachment 項目動態——Any 警告一律關，其餘照檢）

import json
from pathlib import Path
from typing import Any, cast

from . import context

# 可抽取嘅圖片副檔名（其他檔＝跳過）
IMAGE_SUFFIXES = [".png", ".jpg", ".jpeg", ".webp", ".gif", ".bmp"]

# 舊式側車／縮圖標記（唔當原圖）
SIDECAR_MARKERS = [".thumb.", ".meta."]


def read_meta(box_dir: Path) -> dict[str, Any] | None:
    """讀箱內 meta.json；缺失／壞檔 → None。"""
    meta_file = box_dir / "meta.json"

    # 判定：meta 唔存在 → None
    if meta_file.is_file() is False:
        return None

    try:
        meta_data = json.loads(meta_file.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return None

    # 判定：解析結果唔係物件 → None
    if isinstance(meta_data, dict) is False:
        return None

    return cast(dict[str, Any], meta_data)


def write_status(box_dir: Path, new_status: str) -> bool:
    """只改 meta.json 嘅 status（其他 key 零改動；原子寫）。"""
    meta_data = read_meta(box_dir)

    # 判定：meta 讀唔到 → 唔改
    if meta_data is None:
        return False

    meta_data["status"] = str(new_status)

    meta_file = box_dir / "meta.json"
    temp_file = box_dir / "meta.json.tmp"

    try:
        _ = temp_file.write_text(json.dumps(meta_data, ensure_ascii=False, indent=2), encoding="utf-8")
        _ = temp_file.replace(meta_file)
    except OSError:
        return False

    return True


def resolve_box_dir(project_slug: str, chat_sid: str, message_id: int) -> Path | None:
    """由身份搵箱（polling 先、archive 後）；搵唔到 → None。"""
    for stage in ("polling", "archive"):
        chat_dir = context.MESSAGES_DIR / stage / project_slug / chat_sid

        # 判定：對話目錄唔存在 → 下一個階段
        if chat_dir.is_dir() is False:
            continue

        for prompt_dir in sorted(chat_dir.iterdir()):
            # 判定：唔係目錄 → 跳過
            if prompt_dir.is_dir() is False:
                continue

            meta_data = read_meta(prompt_dir)

            # 判定：meta 讀唔到 → 跳過
            if meta_data is None:
                continue

            # 判定：message_id 相符 → 就係呢個箱
            if int(meta_data.get("message_id", 0)) == int(message_id):
                return prompt_dir

    return None


def list_attachment_files(box_dir: Path, meta_data: dict[str, Any]) -> list[dict[str, Any]]:
    """由 meta.attachments 砌工作清單（idx 1 起；缺檔／非圖片／縮圖＝跳過）。"""
    attachments_store = str(meta_data.get("attachments_dir", "") or "")
    attachments_dir = box_dir / "attachments"

    # 判定：meta 有記永久附件庫 → 用永久庫（出帶後附件唔再住箱內）
    if attachments_store != "":
        attachments_dir = Path(attachments_store)
    raw_attachments = meta_data.get("attachments", [])

    # 判定：attachments 唔係陣列 → 空清單
    if isinstance(raw_attachments, list) is False:
        return []

    attachment_list = cast(list[Any], raw_attachments)
    job_files: list[dict[str, Any]] = []

    for raw_item in attachment_list:
        # 判定：單項唔係物件 → 跳過
        if isinstance(raw_item, dict) is False:
            continue

        file_name = str(raw_item.get("filename", ""))

        # 判定：冇檔名 → 跳過
        if file_name == "":
            continue

        # 判定：係縮圖／側車 → 跳過
        is_sidecar = any(marker in file_name for marker in SIDECAR_MARKERS)

        if is_sidecar is True:
            continue

        file_path = attachments_dir / file_name

        # 判定：副檔名唔係圖片 → 跳過
        if file_path.suffix.lower() not in IMAGE_SUFFIXES:
            continue

        # 判定：實檔唔存在 → 跳過
        if file_path.is_file() is False:
            continue

        file_index = len(job_files) + 1

        job_files.append(
            {
                "idx": file_index,
                "path": str(file_path.resolve()),
                "mime": str(raw_item.get("mime_type", "")),
            }
        )

    return job_files

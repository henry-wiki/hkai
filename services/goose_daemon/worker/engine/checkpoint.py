#!/usr/bin/env python3
"""
checkpoint.py

對話同步快照（單一工作：讀／寫快照檔——唔判斷、唔查 DB）。

檔：<storage>/localdb/checkpoints/<project_slug>/<chat_sid>.json
形：{"curr": {"stats": {...}, "checksum": md5}, "prev": {...}}

上游：conversation_sync（發問前讀、同步後寫）
下游：engine_context（路徑）＋ 檔案系統
"""

import hashlib
import json
import os

from . import engine_context


def read(project_slug: str, chat_sid: str) -> dict | None:
    """讀快照 curr stats（缺失／損壞／checksum 唔對＝None——上層當有改動）。"""
    checkpoint_file = engine_context.resolve_checkpoint_file(project_slug, chat_sid)

    try:
        payload = json.loads(checkpoint_file.read_text(encoding="utf-8"))
    except Exception:  # noqa: BLE001
        return None

    # 判定：非 dict → 當冇
    if not isinstance(payload, dict):
        return None

    curr_entry = payload.get("curr")

    # 判定：curr 非 dict → 當冇
    if not isinstance(curr_entry, dict):
        return None

    stats = curr_entry.get("stats")

    # 判定：stats 唔齊（count／max 缺）→ 當冇
    if not isinstance(stats, dict) or "message_count" not in stats or "max_message_id" not in stats:
        return None

    normalized_stats = {
        "message_count": int(stats["message_count"]),
        "max_message_id": int(stats["max_message_id"]),
    }

    # 判定：checksum 唔對（檔壞／被人手改）→ 當冇
    if str(curr_entry.get("checksum", "")) != _stats_checksum(normalized_stats):
        return None

    return normalized_stats


def save(project_slug: str, chat_sid: str, curr_stats: dict, prev_stats: dict | None) -> None:
    """寫快照（curr→prev 移位；原子寫——讀者唔會見到半截檔）。"""
    checkpoint_file = engine_context.resolve_checkpoint_file(project_slug, chat_sid)

    curr_entry = {
        "stats": curr_stats,
        "checksum": _stats_checksum(curr_stats),
    }

    # 判定：有舊 curr → 移入 prev；冇（首次）→ prev 留 null
    if prev_stats is None:
        prev_entry = None
    else:
        prev_entry = {
            "stats": prev_stats,
            "checksum": _stats_checksum(prev_stats),
        }

    payload = {
        "curr": curr_entry,
        "prev": prev_entry,
    }

    checkpoint_file.parent.mkdir(parents=True, exist_ok=True)
    temporary_file = checkpoint_file.with_suffix(checkpoint_file.suffix + ".tmp")
    temporary_file.write_text(json.dumps(payload, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
    os.replace(temporary_file, checkpoint_file)


def _stats_checksum(stats: dict) -> str:
    """統計 checksum（md5——"count:max"；檔案完整性自檢用）。"""
    canonical_text = str(stats["message_count"]) + ":" + str(stats["max_message_id"])
    return hashlib.md5(canonical_text.encode("utf-8")).hexdigest()

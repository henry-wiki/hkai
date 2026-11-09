#!/usr/bin/env python3
"""event_log.py — image_extractor 事件記錄（append-only JSON line）。

職責：寫 data/logs/image_extractor.log（5 MiB 上限，滿即輪替 .1——交 bootstrap/sc_logger.rotate_if_needed）
上游：extractor、main
下游：context（LOG_FILE）、bootstrap/sc_logger（輪替）、json／time（stdlib）
"""

import json
import time
from typing import Any

from bootstrap import sc_logger

from .. import context


def log_event(event: str, message: str, metadata: dict[str, Any] | None = None) -> None:
    """寫一行事件（失敗即靜默——log 唔可以阻斷抽取）。"""
    context.LOG_DIR.mkdir(parents=True, exist_ok=True)
    sc_logger.rotate_if_needed(context.LOG_FILE)

    log_line: dict[str, Any] = {
        "datetime": time.strftime("%Y-%m-%d %H:%M:%S"),
        "source": "image_extractor",
        "event": event,
        "message": message,
    }

    # 判定：有附加資料 → 合併入去
    if metadata is not None:
        log_line["metadata"] = metadata

    try:
        with context.LOG_FILE.open("a", encoding="utf-8", newline="") as log_handle:
            log_handle.write(json.dumps(log_line, ensure_ascii=False) + "\n")
    except OSError:
        return

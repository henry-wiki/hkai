# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false, reportUnusedCallResult=false
#!/usr/bin/env python3
"""
sc_logger.py — image_extractor 執行日誌（append-only JSON line）。

職責：寫 data/logs/image_extractor.log（單檔 5 MiB 上限，滿即輪替為 .1）
上游：main／extractor／runtime.pidfile
下游：context（LOG_FILE）、json／time（stdlib）
"""

import json
import time
from typing import Any

from .. import context


def log(event: str, message: str, metadata: dict[str, Any] | None = None) -> None:
    """寫一行 log（失敗即靜默——log 唔可以阻斷抽取）。"""
    context.LOG_DIR.mkdir(parents=True, exist_ok=True)
    _rotate_if_needed()

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


def _rotate_if_needed() -> None:
    """單檔超過上限 → 改名 .1（舊 .1 直接覆蓋）。"""
    # 判定：檔案未存在 → 唔使輪替
    if context.LOG_FILE.is_file() is False:
        return

    # 判定：未達上限 → 唔使輪替
    if context.LOG_FILE.stat().st_size < context.LOG_MAX_BYTES:
        return

    backup_file = context.LOG_FILE.with_suffix(".log.1")

    try:
        backup_file.unlink(missing_ok=True)
        _ = context.LOG_FILE.replace(backup_file)
    except OSError:
        return

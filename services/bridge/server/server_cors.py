#!/usr/bin/env python3
"""
server_cors.py — CORS 允許清單（server 層政策；兩個 SSE router 共用）

職責：
- read_origin_allowlist(config)：白名單檔優先，缺檔／空檔 → 靜態設定 stream.allowed_origins
- origin_is_allowed(origin, allowed_origins)：判斷單一 origin（支援 "*" 與 "http://host:*"）

上游：server_stream.stream_output、server_presence.presence_stream
下游：context.DATA_DIR（bridge/origin.whitelist）＋ runtime.json（static_config_path → hkai_config.json）
"""

import json
from pathlib import Path

from ..context import DATA_DIR, PROGRAM_NAME


def read_origin_allowlist(config: dict) -> list:
    """讀 CORS 允許清單（data/{PROGRAM_NAME}/origin.whitelist 優先；缺檔／空檔 → 回設定檔清單）。"""
    whitelist_path = DATA_DIR / PROGRAM_NAME / "origin.whitelist"

    whitelist = []

    # 判定：白名單檔存在 → 逐行讀（# 開頭＝註釋；空白行跳）
    if whitelist_path.is_file():
        try:
            for whitelist_line in whitelist_path.read_text(encoding="utf-8").splitlines():
                whitelist_line = whitelist_line.strip()

                if whitelist_line == "" or whitelist_line.startswith("#"):
                    continue

                whitelist.append(whitelist_line)
        except OSError:
            whitelist = []

    # 判定：白名單有內容 → 直接用
    if len(whitelist) > 0:
        return whitelist

    config_path = Path(str(config.get("static_config_path", "")))

    # 判定：設定檔唔存在 → 空清單
    if config_path.is_file() is False:
        return []

    try:
        config_data = json.loads(config_path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return []

    stream_config = config_data.get("stream", {})

    return list(stream_config.get("allowed_origins", []))


def origin_is_allowed(origin: str, allowed_origins: list) -> bool:
    """判斷 origin 是否獲准（支援 "*"＝全開；萬用埠：http://127.0.0.1:*）。"""
    # 判定：清單有 "*" → 全部放行
    if "*" in allowed_origins:
        return True

    for allowed_origin in allowed_origins:
        # 判定：完全相符 → 准
        if allowed_origin == origin:
            return True

        # 判定：通配埠（結尾 :*）→ 前綴相符即准
        if allowed_origin.endswith(":*") and origin.startswith(allowed_origin[:-1]):
            return True

    return False

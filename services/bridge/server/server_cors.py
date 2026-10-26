#!/usr/bin/env python3
"""
server_cors.py — CORS 允許清單（server 層政策；兩個 SSE router 共用）

職責：
- read_origin_allowlist()：讀 data/{PROGRAM_NAME}/origin.whitelist（缺檔／空檔 → 空清單）
- origin_is_allowed(origin, allowed_origins)：判斷單一 origin（支援 "*" 與 "http://host:*"）

上游：server_stream.stream_output、server_presence.presence_stream
下游：context.DATA_DIR（bridge/origin.whitelist）
"""


from ..context import DATA_DIR, PROGRAM_NAME


def read_origin_allowlist() -> list[str]:
    """讀 CORS 允許清單（data/{PROGRAM_NAME}/origin.whitelist；缺檔／空檔 → 空清單）。"""
    whitelist_path = DATA_DIR / PROGRAM_NAME / "origin.whitelist"

    whitelist: list[str] = []

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

    return whitelist


def origin_is_allowed(origin: str, allowed_origins: list[str]) -> bool:
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

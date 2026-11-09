"""resolve_host_suffix.py — 頻道後綴（dev／staging／production）。"""

import os


def resolve_host_suffix() -> str:
    """回傳頻道後綴：host 以 .dev.internal 結尾＝-dev；.staging.internal＝-staging；其餘（含 production）＝空。"""
    # 去埠（HTTP_HOST 帶 :12484——否則尾綴判斷永遠 false）
    request_host = os.environ.get("HTTP_HOST", "").strip().lower()
    request_host = request_host.split(":")[0]

    # 判定：dev 站
    if request_host.endswith(".dev.internal"):
        return "-dev"

    # 判定：staging 站
    if request_host.endswith(".staging.internal"):
        return "-staging"

    # production 用基底資料根（data——同發佈版一致）→ 空後綴
    return ""

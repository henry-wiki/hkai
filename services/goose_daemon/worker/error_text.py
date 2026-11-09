"""error_text.py — 錯誤偵測（一條規則＝一個獨立偵測器）。

規則檔：error_rules.json（每條：name／keywords／http_status／code／display）
- 命中 → 回 (code, "[CODE] 顯示字", True)
- 全部唔中 → 回 ("", 原文, False)（原樣顯示 provider 訊息）
"""

import datetime
import json
import re
from pathlib import Path

_RULES_PATH = Path(__file__).resolve().parent / "error_rules.json"

_ERROR_MARKERS = [
    "ran into this error",
    "request failed:",
    "error code:",
    "api error",
    "not available for free",
    "unavailable for free",
    "model is unavailable",
    "authentication required",
    "invalid api key",
    "incorrect api key",
    "unauthorized",
]


def _load_rules():
    """讀規則檔（每次重讀——加規則唔使重啟）。"""
    payload = json.loads(_RULES_PATH.read_text(encoding="utf-8"))
    return payload.get("rules", [])


def looks_like_error(raw_text):
    """判斷回覆文字係唔係 provider 錯誤。"""
    lowered = raw_text.lower()
    return any(marker in lowered for marker in _ERROR_MARKERS)


def detect_error_code(raw_text):
    """回命中規則嘅 code（冇命中 → 空字串）。"""
    _code, _display, _matched = classify_error(raw_text)
    return _code


def classify_error(raw_text, http_status=0):
    """逐條規則比對；回 (code, display_text, matched)。"""
    lowered = raw_text.lower()

    # 判定：呼叫端冇提供 status → 由文字抽（例 "Resource not found (404)"）
    if http_status == 0:
        status_match = re.search(r"\((\d{3})\)", raw_text)

        if status_match is not None:
            http_status = int(status_match.group(1))

    for rule_item in _load_rules():
        rule_status = int(rule_item.get("http_status", 0))
        rule_keywords = [str(item).lower() for item in rule_item.get("keywords", [])]

        if rule_status != 0 and http_status != rule_status:
            continue

        if len(rule_keywords) > 0 and not any(key in lowered for key in rule_keywords):
            continue

        code = str(rule_item.get("code", ""))
        title_text = str(rule_item.get("title", ""))
        brief_text = str(rule_item.get("brief", ""))
        detail_text = str(rule_item.get("detail", ""))

        # 判定：規則未分層 → 用 display 單句（向後兼容）
        if title_text == "":
            display_text = "⚠️ **[" + code + "] " + str(rule_item.get("display", "")) + "**"
            return code, display_text, True

        title_line = "**⚠️ " + title_text + " · " + code + "**"
        display_text = title_line + chr(10) + chr(10) + brief_text + "。" + chr(10) + chr(10) + detail_text
        return code, display_text, True

    return "", raw_text, False


def append_agent_error_log(log_path, message_id, provider_id, model_id, raw_text, code):
    """完整原文寫入 agent-errors.log（每行 JSON）。"""
    record = {
        "ts": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "message_id": message_id,
        "provider": provider_id,
        "model": model_id,
        "code": code,
        "raw": raw_text,
    }

    record_line = json.dumps(record, ensure_ascii=False) + "\n"

    with open(log_path, "a", encoding="utf-8") as log_handle:
        log_handle.write(record_line)

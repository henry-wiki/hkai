#!/usr/bin/env python3
"""
litellm_secure.py

🧩 可拆模組——litellm 安全與重複內容防護（復刻舊 chat_daemon/adapter/litellm_secure.py）。

職責單一（兩件）：
    1. 敏感值遮蔽／過長截斷（stream_log 淨化——sanitize_for_log）
    2. 摘要止損（推理重複偵測／summarize token 超限——
       is_summarize_repeated／get_summarize_token_limit_reason）

使用（litellm_hook.py）：
    - kwargs 記錄前先 sanitize_for_log（敏感值唔入 log）
    - 摘要串流逐 chunk 檢查止損（觸發＝記事件＋終止子程序——切斷上游串流）

移除方法：直接刪咗本檔——litellm_hook try-import 唔到 → 淨化／止損自動停用，
其餘照跑（零代碼改動）。
"""

import re

from ..msg.msg_events import (
    EVENT_SUMMARY_RETRY_SCHEDULED,
    EVENT_SUMMARY_RETRY_TRIGGERED,
)

# ============================================================
# 推理重複偵測常數（reasoning repetition detection）
#
# 目的：部分推理模型（nex-n2.5-mini/pro）會在 reasoning_content
#       中進入無限迴圈（同一 phrase 重複數千次），導致
#       msg_summarize txt 檔膨脹至數百 KB（實測 540K），
#       且用戶為無意義的垃圾 token 付費。
#
# 偵測原理（取尾部視窗 → 正規化 → substring match）：
#   1. 取尾部 2N 字視窗（空白會被移除，取 2 倍確保正規化後長度足夠；只對視窗處理，避免 O(N²)）
#   2. 正規化：數字 → 遮罩字元、空白 → 移除（容忍遞增編號／排版差異）
#   3. 取視窗尾部 M 字為「樣本」，其前 N 字為「模板」
#   4. 樣本在模板中出現 ≥ K 次 → 判定為重複 → break 迴圈 + 止損
#
# 值的由來：
#   - 6000 字 buffer（REASONING_BUFFER_MAX）：足夠覆蓋 1 個重複 phrase（~150 字）多次
#   - 130 字 sample（REASONING_SAMPLE_LEN）：小於最短重複單位（~150 字），確保完整覆蓋
#   - 6 次門檻（REPEAT_THRESHOLD）：遠小於實測重複次數（3600+），但大於正常推理的
#     偶爾重複（模型自我校正 2-3 次，不會到 6 次）
#
# 業界定位：非標準方案（標準 = provider 端 max_tokens），
#   本機制是 consumer 端 defense-in-depth，與 max_tokens + timeout 互補。
#   觸發後：txt 小（reasoning buffer 上限）、stream 關（計費止損）、
#   full_text 可能為空 → ChatSummary 繼續跑（不 crash）。
# ============================================================
REASONING_BUFFER_MAX = 6000
REASONING_SAMPLE_LEN = 130
REPEAT_THRESHOLD = 6

# 重複偵測遮罩字元（數字正規化用——PUA 罕見字元，正常文字幾乎不出現，降低誤判）
REPEAT_MASK_CHAR = "\uE000"

# summarize 輸出 token 上限（估算——超過即中止，避免無意義的長輸出）
# 此為內建兜底——manifest aider_daemon.summarize_token_abort 之正常正數優先取代（缺／異常回此值）
SUMMARIZE_TOKEN_ABORT = 60000
# 估算比例（字元 / token——不需準確；中文偏多，取小值較保守）
SUMMARIZE_CHARS_PER_TOKEN = 2

# stream_log 單一值長度上限（字元——超過則截斷並標示原始長度）
STREAM_LOG_VALUE_MAX_CHARS = 1000

# stream_log 敏感關鍵字（key 名含其一 → 值以 ***redacted*** 取代）
STREAM_LOG_REDACT_KEYWORDS = ("api_key", "authorization", "token", "secret", "password")
REDACTED_TEXT = "***redacted***"


def is_sensitive_key(key) -> bool:
    """判斷 key 名是否含敏感關鍵字（不分大小寫）。"""
    key_text = str(key).lower()

    # 逐一比對敏感關鍵字
    for keyword in STREAM_LOG_REDACT_KEYWORDS:
        # 判定：key 名含該關鍵字 → 敏感
        if keyword in key_text:
            return True
    return False


def sanitize_for_log(value):
    """遞迴淨化 stream_log 的值：敏感 key → ***redacted***；字串過長 → 截斷；dict／list → 逐項處理。"""
    # 判定：dict → 逐 key 淨化（敏感 key 的值以 ***redacted*** 取代）
    if isinstance(value, dict):
        sanitized_dict = {}
        for key, item in value.items():
            # 判定：key 名命中敏感關鍵字 → 值以 ***redacted*** 取代（保留欄位）
            if is_sensitive_key(key):
                sanitized_dict[key] = REDACTED_TEXT
                continue
            sanitized_dict[key] = sanitize_for_log(item)
        return sanitized_dict

    # 判定：list → 逐項淨化
    if isinstance(value, list):
        return [sanitize_for_log(item) for item in value]

    # 判定：str → 依長度截斷
    if isinstance(value, str):
        # 判定：未超過上限 → 原樣保留
        if len(value) <= STREAM_LOG_VALUE_MAX_CHARS:
            return value
        # 超過上限 → 截斷並標示原始字元數
        return value[:STREAM_LOG_VALUE_MAX_CHARS] + f"...({len(value)} chars)"

    return value


def get_summarize_repeat_count(summarize_content: str) -> int:
    """計算尾部 sample 在 template 中的重複次數（取 ×2 視窗→正規化→切 sample/template；含量測 log）。"""
    # 取尾部視窗（BUFFER_MAX × 2——空白會被移除，取 2 倍確保正規化後長度足夠；只對視窗正規化，避免 O(N²)）
    _raw_window = summarize_content[-(REASONING_BUFFER_MAX * 2):]

    # 正規化：數字 → 罕見遮罩字元；空白 → 移除（降低「只差一字元」造成的漏判）
    _normalized = re.sub(r"\d+", REPEAT_MASK_CHAR, _raw_window)
    _normalized = re.sub(r"\s+", "", _normalized)

    _sample = ""
    # 判定：視窗長度足夠 → 取尾部 sample
    if len(_normalized) >= REASONING_SAMPLE_LEN:
        _sample = _normalized[-REASONING_SAMPLE_LEN:]

    _template = ""
    # 判定：視窗長度足夠 → 取 sample 之前的 template
    if len(_normalized) >= REASONING_BUFFER_MAX + REASONING_SAMPLE_LEN:
        _template = _normalized[-(REASONING_BUFFER_MAX + REASONING_SAMPLE_LEN):-REASONING_SAMPLE_LEN]

    # 判定：sample 或 template 為空 → 無從判定
    if not _sample or not _template:
        return 0

    _match_count = _template.count(_sample)
    # 判定：有命中 → 記量測 log
    if _match_count > 0:
        _msg_log().append_info_log(
            EVENT_SUMMARY_RETRY_SCHEDULED,
            f"Found repeated: sample appeared {_match_count} times in template",
        )
    return _match_count


def is_summarize_repeated(summarize_thinking: str, summarize_result: str) -> bool:
    """thinking + result 各自獨立檢查，任一超標即記錄 error log 並回傳 True（內容由呼叫端傳入——不讀檔）。"""
    _match_thinking_count = get_summarize_repeat_count(summarize_thinking)
    _match_result_count = get_summarize_repeat_count(summarize_result)

    _match_count = max(_match_thinking_count, _match_result_count)

    # 判定：重複次數達門檻 → 記 error log 並回報「重複」
    if _match_count >= REPEAT_THRESHOLD:
        _msg_log().append_error_log(
            EVENT_SUMMARY_RETRY_TRIGGERED,
            f"Found repeated :（match count: {_match_count}）",
        )
        return True
    return False


def estimate_token_count(text: str) -> int:
    """估算 token 數（字元數 / SUMMARIZE_CHARS_PER_TOKEN——不追求準確）。"""
    return len(text) // SUMMARIZE_CHARS_PER_TOKEN


def get_summarize_token_abort() -> int:
    """取 summarize 中止門檻（daemon conf 設定優先；缺／不正常數字 → 內建常數兜底）。"""
    daemon_summarize_token_abort = _daemon().get_daemon_conf_data().get("daemon_summarize_token_abort")
    # 判定：布林值（非正常數字——True／False 為 int 子類，須先攔）→ 回內建常數
    if isinstance(daemon_summarize_token_abort, bool):
        return SUMMARIZE_TOKEN_ABORT
    # 判定：非數字（缺 key／字串／None……）→ 回內建常數
    if not isinstance(daemon_summarize_token_abort, (int, float)):
        return SUMMARIZE_TOKEN_ABORT
    # 判定：非正數（0／負數——不正常數字）→ 回內建常數
    if daemon_summarize_token_abort <= 0:
        return SUMMARIZE_TOKEN_ABORT
    return int(daemon_summarize_token_abort)


def get_summarize_token_limit_reason(summarize_thinking: str, summarize_result: str) -> str:
    """回傳超限來源：''＝未超；'thinking'／'result'（thinking 優先——門檻取設定優先值）。"""
    summarize_token_abort = get_summarize_token_abort()
    # 判定：thinking 估算超過門檻 → 回 thinking
    if estimate_token_count(summarize_thinking) > summarize_token_abort:
        return "thinking"
    # 判定：result 估算超過門檻 → 回 result
    if estimate_token_count(summarize_result) > summarize_token_abort:
        return "result"
    return ""


# ==========================================
# 延遲取用（非 daemon 拉起時——本模組零載入負擔）
# ==========================================
def _msg_log():
    """msg_log 單例。"""
    from ..context import msg_log

    return msg_log()


def _daemon():
    """daemon_manager 單例。"""
    from ..context import daemon

    return daemon()

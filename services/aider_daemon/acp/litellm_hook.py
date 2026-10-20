#!/usr/bin/env python3
"""
litellm_hook.py

🧩 可拆模組——litellm 呼叫攔截器（復刻舊 chat_daemon/adapter/litellm_hook.py；行為契約＝
fsm_map.json「stream_log」條＋.docs/msg-log-event-catalog.md）。

職責（本模組＝以下產物嘅唯一寫入者；主回覆段檔仍由 worker 寫，本模組唔碰）：
    1. 每個 completion → msg_stream_log.jsonl 逐回呼一行
       （kind：stream_response／stream_summarize／finished／other）
    2. 摘要請求（aider 以非串流發出——prompts.py summarize 前綴偵測）→ 升級為串流：
       逐 chunk 寫 msg_summarize.0.thinking（reasoning）／msg_summarize.0.result（content），
       並以 stream_chunk_builder 組返非串流外觀回應（呼叫端無感）
    3. 事件：INPUT_RECEIVED／REPLY_STREAM_STARTED（每 completion）＋
       SYS_STREAM_UPGRADE／SUMMARY_STREAM_*（摘要——OPENED／CLOSED 帶狀態轉移）

安全底線（通道保真——最高原則）：
    偵測失敗＝照原 passthrough；升級失敗（零 chunk）＝退回原非串流呼叫；
    任何記錄寫入異常一律吞掉——永不改變 aider 原本輸出。
    摘要止損（推理重複／token 超限——litellm_secure 🧩）：觸發＝記事件＋終止子程序。

安裝（main.py 嘅可拆邊界 try/except 呼叫）：
    setup() 讀環境 AIDER_ACP_RUNTIME_PATH／AIDER_ACP_MSG_CONF_PATH（aider_entry spawn 帶入）；
    兩值缺一＝inert（如 smoke_client 手動跑——零副作用）。
"""

import os
import threading
from pathlib import Path

# 🧩 可拆模組：litellm_secure（安全淨化＋摘要止損——缺席＝照跑，防護層停用）
try:
    from . import litellm_secure as litellm_secure_module
except ImportError:
    litellm_secure_module = None

# 摘要請求偵測前綴（aider/prompts.py「summarize」——user 前嘅首則 system 以佢開頭）
_SUMMARIZE_PROMPT_PREFIX = "*Briefly* summarize"

# 首段預覽長度（start 記錄 first_content_prefix——與舊制一致 80 字）
_FIRST_CONTENT_PREFIX_LENGTH = 80

# 硬性供應商錯誤收尾標記（reason 前綴——_abort_summarize_stream 分流用）
_TERMINAL_ERROR_PREFIX = "terminal_error:"

# 安裝狀態（整程序一次）
_installed = False
_original_completion = None
_install_lock = threading.Lock()

# 已見 response_id（首 chunk 記錄＋STARTED 事件去重——per response_id 一次）
_seen_response_ids: set = set()


def setup() -> None:
    """開機設定（main.py 呼叫）：載入語境（runtime／msg_conf）→ 安裝攔截器。環境缺值＝跳過。"""
    _runtime_path = os.environ.get("AIDER_ACP_RUNTIME_PATH", "")
    _msg_conf_path = os.environ.get("AIDER_ACP_MSG_CONF_PATH", "")

    # 判定：任一環境值缺 → 非 daemon 拉起（inert——零副作用）
    if _runtime_path == "" or _msg_conf_path == "":
        return

    from ..context import daemon, msg_main
    from ..runtime import sc_logger

    try:
        # runtime.json（is_reload=True——跳過 boot 宣告／keys；messages_dir 等路徑展開用）
        daemon().load_daemon_conf_data(Path(_runtime_path), is_reload=True)
        # msg_conf.json（本訊息語境——message_id／chat_id／project_slug）
        msg_main().load_msg_conf_data(Path(_msg_conf_path))
    except Exception as exc:  # noqa: BLE001
        # 語境失敗 → 唔攔截（aider 照跑——嚴格安全退化）
        sc_logger.log("litellm_hook: context load failed: %s" % (exc,))
        return

    install_completion_interceptor()


def install_completion_interceptor() -> None:
    """包 litellm.completion（模組屬性——aider 全部呼叫走 litellm.completion(...)，patch 屬性全中）。"""
    global _installed
    global _original_completion

    with _install_lock:
        # 判定：已裝 → 唔重複包（幂等）
        if _installed:
            return

        import litellm

        _original_completion = litellm.completion
        litellm.completion = _completion_interceptor
        _installed = True


def _completion_interceptor(*args, **kwargs):
    """攔截入口：分類 → 記錄 → 原呼叫（串流 tap／摘要升級／旁支 passthrough）。"""
    return _intercept(_original_completion, args, kwargs)


def _intercept(original_completion, args, kwargs):
    """分類路由：摘要 → 升級路徑；串流主回覆 → tap；其餘非串流 → 旁支記錄。"""
    _messages = _extract_messages(kwargs)
    _is_summarize = _is_summarize_request(_messages)

    # 判定：摘要請求 → 升級串流路徑（無論原 stream 值——升級對已串流者為冪等）
    if _is_summarize:
        return _run_summarize(original_completion, args, kwargs, _messages)

    _is_stream = bool(kwargs.get("stream"))

    # 判定：主回覆串流 → tap（逐 chunk 記錄；輸出原樣通過）
    if _is_stream:
        _log_input_received(_messages)
        _log_call_start(kwargs, _messages, "stream_response")
        # 用量入最終 chunk（include_usage——舊制主回覆 finished.usage 之來源；部分供應商唔主動報）
        _stream_kwargs = dict(kwargs)
        _stream_options = dict(_stream_kwargs.get("stream_options") or {})
        _stream_options["include_usage"] = True
        _stream_kwargs["stream_options"] = _stream_options
        _stream = original_completion(*args, **_stream_kwargs)
        return _tap_main_stream(_stream, kwargs)

    # 其餘非串流（旁支——如 commit message）→ 只寫 {kind, usage}
    _log_input_received(_messages)
    _response = original_completion(*args, **kwargs)
    _log_side_call(_response)
    return _response


# ==========================================
# 摘要升級路徑（aider 非串流請求 → 串流化記錄 → 組返非串流外觀回應）
# ==========================================
def _run_summarize(original_completion, args, kwargs, messages):
    """摘要請求全流程：升級 → 逐 chunk 記錄＋寫檔 → 組返回應；失敗安全退化。"""
    _log_input_received(messages)
    _log_call_start(kwargs, messages, "stream_summarize")

    _upgraded_kwargs = dict(kwargs)

    # 判定：原本非串流 → 記升級事件（原本已串流＝冇升級動作）
    _was_stream = bool(kwargs.get("stream"))

    if not _was_stream:
        _trace("SYS_STREAM_UPGRADE", "Upgraded to stream")

    # 升級：stream=True ＋ 用量入 chunk（include_usage）
    _upgraded_kwargs["stream"] = True
    _stream_options = dict(_upgraded_kwargs.get("stream_options") or {})
    _stream_options["include_usage"] = True
    _upgraded_kwargs["stream_options"] = _stream_options

    _transition("SUMMARY_STREAM_OPENED", "Summarize stream opened")

    _chunks = []
    _chunk_index = 0
    _summarize_thinking_text = ""
    _summarize_result_text = ""
    _reasoning_stopped = ""
    _usage = None
    _finish_reason = ""
    _last_choices = []

    try:
        _stream = original_completion(*args, **_upgraded_kwargs)

        for _chunk in _stream:
            _view = _chunk_view(_chunk)
            _maybe_first_chunk("stream_summarize", _view, kwargs)
            _log_chunk("stream_summarize", _chunk_index, _view)

            # 文字分流：reasoning → thinking 檔；content → result 檔
            _append_summarize_thinking(_view["reasoning_text"])
            _append_summarize_result(_view["content_text"])

            # 累積（止損偵測用——內容由記憶體傳入，唔讀檔）
            _summarize_thinking_text += _view["reasoning_text"]
            if _view["content_text"]:
                _summarize_result_text += _view["content_text"]

            # 判定：止損（推理重複／token 超限）→ 跳出（loop 後記事件＋終止子程序）
            _stop_reason = _summarize_stop_reason(_summarize_thinking_text, _summarize_result_text)
            if _stop_reason != "":
                _reasoning_stopped = _stop_reason
                break

            if _view["usage"] is not None:
                _usage = _view["usage"]

            if _view["finish_reason"] != "":
                _finish_reason = _view["finish_reason"]

            _last_choices = _view["choices"]
            _chunks.append(_chunk)
            _chunk_index += 1

    except BaseException as exc:  # noqa: BLE001
        # 判定：硬性供應商錯誤（rate limit 封頂／認證／模型唔存在——重試無意義）→ 即刻收尾（記明原因＋終止子程序）
        if _is_terminal_provider_error(exc):
            _abort_summarize_stream(_TERMINAL_ERROR_PREFIX + _short_error_text(exc))

        # 判定：零 chunk（升級本身失敗）→ 退回原非串流呼叫（aider 原行為）
        if _chunk_index == 0:
            return _run_summarize_fallback(original_completion, args, kwargs, messages)

        # 已有 chunk 後斷流 → 真失敗：記事件＋照拋（原始異常語義）
        _transition("SUMMARY_STREAM_FAILED", "Summarize stream failed : %s" % (exc,))
        raise

    # 判定：止損觸發（重複／超限）→ 記事件＋終止子程序（唔組返回應——上游串流即斷）
    if _reasoning_stopped != "":
        _abort_summarize_stream(_reasoning_stopped)

    _response = _assemble_stream_response(_chunks)

    # 判定：組唔返回應（不可達異常）→ 退回非串流（aider 要有合規回應先得）
    if _response is None:
        return _run_summarize_fallback(original_completion, args, kwargs, messages)

    _full_text = _summarize_result_text
    _log_finished(_chunk_index, _finish_reason, _full_text, _usage, _last_choices)
    _transition("SUMMARY_STREAM_CLOSED", "Summarize stream closed : （%d Chars）" % len(_full_text))
    return _response


def _run_summarize_fallback(original_completion, args, kwargs, messages):
    """升級失敗退化：原非串流重發 → 一次性寫檔（摘要照樣捕捉）→ 記完成。"""
    _response = original_completion(*args, **kwargs)

    _reasoning_text = ""
    _content_text = ""
    _choices = getattr(_response, "choices", None) or []

    for _choice in _choices:
        _message = getattr(_choice, "message", None)

        if _message is None:
            continue

        _reasoning_piece = getattr(_message, "reasoning_content", None)

        if isinstance(_reasoning_piece, str):
            _reasoning_text += _reasoning_piece

        _content_piece = getattr(_message, "content", None)

        if isinstance(_content_piece, str):
            _content_text += _content_piece

    _append_summarize_thinking(_reasoning_text)
    _append_summarize_result(_content_text)

    _response_id = getattr(_response, "id", "") or ""

    if _response_id != "":
        _trace("SUMMARY_STREAM_STARTED", "Summarize stream started : id=%s" % _response_id)

    _usage = getattr(_response, "usage", None)

    if _usage is not None and hasattr(_usage, "model_dump"):
        _usage = _usage.model_dump()

    _finish_reason = ""
    _choices_dump = []

    for _choice in _choices:
        _finish = getattr(_choice, "finish_reason", None)

        if _finish:
            _finish_reason = _finish

        if hasattr(_choice, "model_dump"):
            _choices_dump.append(_choice.model_dump())
        else:
            _choices_dump.append(_choice)

    _log_finished(0, _finish_reason, _content_text, _usage, _choices_dump)
    _transition("SUMMARY_STREAM_CLOSED", "Summarize stream closed : （%d Chars）" % len(_content_text))
    return _response


def _assemble_stream_response(chunks):
    """chunk 列 → 非串流外觀回應（litellm.stream_chunk_builder；失敗回 None）。"""
    try:
        import litellm

        return litellm.stream_chunk_builder(chunks=chunks)
    except Exception:  # noqa: BLE001
        return None


def _summarize_stop_reason(summarize_thinking_text, summarize_result_text) -> str:
    """止損原因（''＝唔止；'repeat'／'token_limit_thinking'／'token_limit_result'）——litellm_secure 缺席＝零止損。"""
    # 判定：litellm_secure 缺席 → 唔偵測（防護層停用）
    if litellm_secure_module is None:
        return ""

    # 判定：推理重複達門檻 → 止損
    if litellm_secure_module.is_summarize_repeated(summarize_thinking_text, summarize_result_text):
        return "repeat"

    _token_limit_reason = litellm_secure_module.get_summarize_token_limit_reason(summarize_thinking_text, summarize_result_text)
    # 判定：token 估算超限 → 止損（帶來源）
    if _token_limit_reason != "":
        return "token_limit_" + _token_limit_reason

    return ""


def _is_terminal_provider_error(exc) -> bool:
    """硬性供應商錯誤判定（重試無意義：rate limit／認證／權限／模型唔存在）；其餘（網絡／超時等）＝可重試。"""
    import litellm

    _terminal_types = (
        litellm.exceptions.RateLimitError,
        litellm.exceptions.AuthenticationError,
        litellm.exceptions.PermissionDeniedError,
        litellm.exceptions.NotFoundError,
    )

    return isinstance(exc, _terminal_types)


def _short_error_text(exc) -> str:
    """錯誤短句（訊息活動行用——壓成單行＋180 字上限）。"""
    _error_text = " ".join(str(exc).split())

    # 判定：過長 → 截短
    if len(_error_text) > 180:
        _error_text = _error_text[:180] + "…"

    return _error_text


def _abort_summarize_stream(reason) -> None:
    """摘要收尾（止損／硬錯同一條鏈）：記事件 → FSM failed → 關通道 → 終止子程序（切斷上游串流）。"""
    from ..context import exit_with_children

    try:
        # 判定：重複偵測觸發
        if reason == "repeat":
            _msg_log().append_error_log("SUMMARY_RETRY_TRIGGERED", "Summarize stream stopped（Detect infinity loop）")
        # 判定：硬性供應商錯誤（重試無意義）→ 記明原因
        elif reason.startswith(_TERMINAL_ERROR_PREFIX):
            _terminal_detail = reason.removeprefix(_TERMINAL_ERROR_PREFIX)
            _msg_log().append_error_log("SUMMARY_STREAM_FAILED", "Summarize stream failed（Terminal error——Retry will not help）: %s" % (_terminal_detail,))
        # 其餘＝token 估算超限
        else:
            _msg_log().append_error_log("SUMMARY_TOKEN_LIMIT_REACHED", "Summarize stream stopped（%s）" % (reason,))
        _msg_log().append_error_log("FAILURE_RECORDED", "Summarize stream aborted")
        _msg_state_machine().apply_and_report("FAILURE_RECORDED")
        _msg_state_machine().close_channel()
    except Exception:  # noqa: BLE001, S110
        pass

    exit_with_children(1)


# ==========================================
# 主回覆串流 tap（generator——輸出原樣通過，逐 chunk 記錄）
# ==========================================
def _tap_main_stream(stream, kwargs):
    """包主回覆串流：逐 chunk 記錄 → yield 原 chunk；正常收完才寫 finished。"""
    _chunk_index = 0
    _text_parts = []
    _usage = None
    _finish_reason = ""
    _last_choices = []

    for _chunk in stream:
        _view = _chunk_view(_chunk)
        _maybe_first_chunk("stream_response", _view, kwargs)
        _log_chunk("stream_response", _chunk_index, _view)

        if _view["content_text"]:
            _text_parts.append(_view["content_text"])

        if _view["usage"] is not None:
            _usage = _view["usage"]

        if _view["finish_reason"] != "":
            _finish_reason = _view["finish_reason"]

        _last_choices = _view["choices"]
        _chunk_index += 1
        yield _chunk

    # 正常收完（消費者冇提早 close/拋錯）→ 收尾記錄
    _log_finished(_chunk_index, _finish_reason, "".join(_text_parts), _usage, _last_choices)


# ==========================================
# chunk 解讀（litellm chunk → 記錄欄位）
# ==========================================
def _chunk_view(chunk) -> dict:
    """單一 chunk 拆欄：識別（id／model／request_id）＋文字（reasoning／content）＋用量／收尾＋choices dump。"""
    _choices = getattr(chunk, "choices", None) or []
    _choices_dump = []
    _reasoning_text = ""
    _content_text = ""
    _finish_reason = ""

    for _choice in _choices:
        # choices 原樣 dump（外觀與舊制一致——native_finish_reason 等全欄）
        if hasattr(_choice, "model_dump"):
            _choices_dump.append(_choice.model_dump())
        else:
            _choices_dump.append(_choice)

        _delta = getattr(_choice, "delta", None)

        # 判定：有 delta → 摘文字（reasoning_content／content）
        if _delta is not None:
            _reasoning_piece = getattr(_delta, "reasoning_content", None)

            if isinstance(_reasoning_piece, str):
                _reasoning_text += _reasoning_piece

            _content_piece = getattr(_delta, "content", None)

            if isinstance(_content_piece, str):
                _content_text += _content_piece

        _finish = getattr(_choice, "finish_reason", None)

        if _finish:
            _finish_reason = _finish

    _usage = getattr(chunk, "usage", None)

    if _usage is not None and hasattr(_usage, "model_dump"):
        _usage = _usage.model_dump()

    _request_id = getattr(chunk, "request_id", None)

    if _request_id is None:
        _request_id = {}

    return {
        "response_id":  getattr(chunk, "id", "") or "",
        "model":        getattr(chunk, "model", "") or "",
        "request_id":   _request_id,
        "usage":        _usage,
        "choices":      _choices_dump,
        "reasoning_text": _reasoning_text,
        "content_text":   _content_text,
        "finish_reason":  _finish_reason,
    }


# ==========================================
# 分類／拆解（kwargs → 偵測與記錄欄位）
# ==========================================
def _extract_messages(kwargs) -> list:
    """取 messages（缺／非 list → 空 list）。"""
    _messages = kwargs.get("messages", [])

    if not isinstance(_messages, list):
        return []

    return _messages


def _is_summarize_request(messages) -> bool:
    """摘要偵測：首則 role=system 且 content 以 *Briefly* summarize 開頭（失配＝當非摘要——安全側）。"""
    if not messages:
        return False

    _first_message = messages[0]
    _role = _first_message.get("role", "") if isinstance(_first_message, dict) else getattr(_first_message, "role", "")

    if _role != "system":
        return False

    _content = _first_message.get("content", "") if isinstance(_first_message, dict) else getattr(_first_message, "content", "")

    if not isinstance(_content, str):
        return False

    return _content.startswith(_SUMMARIZE_PROMPT_PREFIX)


def _sanitized_kwargs(kwargs) -> dict:
    """kwargs 剔除 messages（fsm_map stream_log 契約——記錄唔帶全文）；其餘經安全淨化（敏感值遮蔽／過長截斷）。"""
    _sanitized = {}

    for _key, _value in kwargs.items():
        if _key == "messages":
            continue

        _sanitized[_key] = _value

    # 判定：litellm_secure 在 → 淨化（敏感 key 值 → ***redacted***；長值截斷）
    if litellm_secure_module is not None:
        _sanitized = litellm_secure_module.sanitize_for_log(_sanitized)

    return _sanitized


def _message_content_chars(message) -> int:
    """單則訊息 content 字數（str 直算；其餘 str() 近似）。"""
    _content = message.get("content", "") if isinstance(message, dict) else getattr(message, "content", "")

    if _content is None:
        return 0

    if isinstance(_content, str):
        return len(_content)

    return len(str(_content))


# ==========================================
# 記錄寫入（全部吞錯——記錄失敗永不影響通道）
# ==========================================
def _log_input_received(messages) -> None:
    """INPUT_RECEIVED（每 completion 一次——含 message_count／total_chars）。"""
    _total_chars = 0

    for _message in messages:
        _total_chars += _message_content_chars(_message)

    _trace(
        "INPUT_RECEIVED",
        "Prompt received (%d messages, %d chars)" % (len(messages), _total_chars),
        {"message_count": len(messages), "total_chars": _total_chars},
    )


def _log_call_start(kwargs, messages, kind) -> None:
    """start 記錄（每呼叫一行——model／stream／message_count／首則預覽／kwargs）。"""
    _first_message = messages[0] if messages else {}
    _first_role = _first_message.get("role", "") if isinstance(_first_message, dict) else getattr(_first_message, "role", "")
    _first_content = _first_message.get("content", "") if isinstance(_first_message, dict) else getattr(_first_message, "content", "")
    _first_content_prefix = _first_content[:_FIRST_CONTENT_PREFIX_LENGTH] if isinstance(_first_content, str) else ""

    _write_stream_record({
        "kind":                kind,
        "model":               kwargs.get("model"),
        "stream":              bool(kwargs.get("stream")),
        "max_tokens":          kwargs.get("max_tokens"),
        "message_count":       len(messages),
        "first_role":          _first_role,
        "first_content_prefix": _first_content_prefix,
        "kwargs":              _sanitized_kwargs(kwargs),
    })


def _maybe_first_chunk(kind, view, kwargs) -> None:
    """首見 response_id：response_id 記錄＋STARTED 事件（per response_id 去重）。"""
    _response_id = view["response_id"]

    # 判定：無 id 或已見過 → 唔記
    if _response_id == "" or _response_id in _seen_response_ids:
        return

    _seen_response_ids.add(_response_id)

    _write_stream_record({
        "kind":        kind,
        "response_id": _response_id,
        "model":       view["model"],
        "request_id":  view["request_id"],
        "choices":     view["choices"],
        "kwargs":      _sanitized_kwargs(kwargs),
    })

    # 判定：摘要頻道 → 摘要 STARTED 文案；主回覆頻道 → 主回覆 STARTED 文案
    if kind == "stream_summarize":
        _trace("SUMMARY_STREAM_STARTED", "Summarize stream started : id=%s" % _response_id)
    else:
        _trace(
            "REPLY_STREAM_STARTED",
            "Stream started : id=%s model=%s" % (_response_id, view["model"]),
            {"response_id": _response_id, "model": view["model"]},
        )


def _log_chunk(kind, chunk_index, view) -> None:
    """chunk 記錄（每個回呼一行——choices 原樣）。"""
    _write_stream_record({
        "kind":        kind,
        "chunk_index": chunk_index,
        "choices":     view["choices"],
    })


def _log_finished(chunk_index, finish_reason, text, usage, choices) -> None:
    """finished 記錄（每呼叫一筆——chunk_index＝chunk 總數；usage＝PHP token 讀取源）。"""
    _record: dict = {
        "kind":          "finished",
        "chunk_index":   chunk_index,
        "finish_reason": finish_reason,
        "delta":         text,
    }

    # 判定：有 usage → 附上（litellm 最終 chunk／非串流回應先有）
    if usage is not None:
        _record["usage"] = usage

    _record["choices"] = choices
    _write_stream_record(_record)


def _log_side_call(response) -> None:
    """旁支記錄（kind=other——只寫 usage，不佔 chunk_index）。"""
    _record: dict = {"kind": "other"}
    _usage = getattr(response, "usage", None)

    if _usage is not None:
        _record["usage"] = _usage.model_dump() if hasattr(_usage, "model_dump") else _usage

    _write_stream_record(_record)


def _write_stream_record(record: dict) -> None:
    """stream_log 寫入（吞錯——datetime 由 msg_log 置前）。"""
    try:
        _msg_log().append_stream_log(record)
    except Exception:  # noqa: BLE001, S110
        pass


def _append_summarize_thinking(text) -> None:
    """摘要 thinking 檔 append（吞錯）。"""
    try:
        _msg_log().append_summarize_thinking(text)
    except Exception:  # noqa: BLE001, S110
        pass


def _append_summarize_result(text) -> None:
    """摘要 result 檔 append（吞錯）。"""
    try:
        _msg_log().append_summarize_result(text)
    except Exception:  # noqa: BLE001, S110
        pass


def _trace(event, message, metadata=None) -> None:
    """純足跡事件（唔驅動狀態——吞錯）。"""
    try:
        _msg_log().append_info_log(event, message, metadata)
    except Exception:  # noqa: BLE001, S110
        pass


def _transition(event, message, metadata=None) -> None:
    """狀態事件：msg_log 記一行＋經 FSM 轉移（子程序側 apply——report 無通道＝no-op）。"""
    try:
        _msg_log().append_info_log(event, message, metadata)
        _msg_state_machine().apply_and_report(event)
    except Exception:  # noqa: BLE001, S110
        pass


# ==========================================
# 延遲取用（非 daemon 拉起時——本模組零載入負擔）
# ==========================================
def _msg_log():
    """msg_log 單例。"""
    from ..context import msg_log

    return msg_log()


def _msg_state_machine():
    """msg_state_machine 單例。"""
    from ..context import msg_state_machine

    return msg_state_machine()


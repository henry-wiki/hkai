#!/usr/bin/env python3
"""
server_stream.py — SSE 串流端點（輸出監控）。

職責：
- build_router()：建立路由器（單一路徑 /bridge_sse_for_message）
- stream_output_for_message()：GET /bridge_sse_for_message（tail 訊息目錄檔案，push SSE 事件）
- _build_message_dir()／_locate_message_dir()：由 identity（project_slug／chat_sid／prompt_id）即時定位目錄
- message.get_msg_status()：讀 msg_status.txt 最後一行（bridge/message 提供）
- _read_appended()：由指定位元偏移讀新增內容（回 (新內容, 新偏移)）
- _format_event()：砌 SSE 幀（event ＋ JSON data）

設計：
1. 只讀檔案契約（msg_status.txt／msg_response.{n}.result.txt／msg_response.{n}.thinking.txt）
2. 唔 import 任何 agent 模組——aider_daemon 同 goose_daemon 寫同一批檔，一套就够
3. 訊息完成後 PHP 會將目錄搬去 archive——每輪用 identity 即時定位（polling → archive），冇快取路徑
"""

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false
# （狀態／job JSON 動態——Unknown/Any 警告一律關，其餘照檢）

import asyncio
import hashlib
import json
import re
import time
from pathlib import Path
from typing import Any

from fastapi import APIRouter, Request
from fastapi.responses import StreamingResponse

from .. import message
from ..context import MESSAGES_ARCHIVE_DIR, MESSAGES_POLLING_DIR
from . import server_daemon, server_image_extract
from .server_cors import origin_is_allowed, read_origin_allowlist

# 終態（msg_status.txt 最後一行——出現即收尾）
TERMINAL_STATUSES = ("completed", "failed", "cancelled", "aborted")

# 輪詢間隔（秒——本機檔案系統，0.25 秒足够貼近即時）
POLL_INTERVAL_SECONDS = 0.25

# 心跳間隔（秒——防止中間代理斷開閒置連線）
HEARTBEAT_INTERVAL_SECONDS = 15

# 單條連線上限（秒——到頂即收尾，前端自動重連）
STREAM_MAX_SECONDS = 900

# project_slug 白名單（防路徑穿越）
PROJECT_SLUG_PATTERN = re.compile(r"^[A-Za-z0-9_-]+$")

# 圖片抽取工作根目錄（每個 message 一個子目錄；一個檔案一個真相）


def build_router() -> APIRouter:
    """建立串流路由器（單一契約：/bridge_sse_for_message）。"""
    router = APIRouter()
    router.add_api_route("/bridge_sse_for_message", stream_output_for_message, methods=["GET"])
    return router


_active_stream_count = 0


def get_active_stream_count() -> int:
    """回傳現時開住嘅 SSE 串流數（bridge 閒置退出用）。"""
    return _active_stream_count


async def _counted_message_event_generator(request: Request, project_slug: str, chat_sid: str, prompt_id: str, response_index: int):
    """包一層：串流開／關時加減計數（bridge 閒置退出唔可以殺死進行中嘅串流）。"""
    global _active_stream_count
    _active_stream_count += 1

    try:
        async for event_chunk in _message_event_generator(request, project_slug, chat_sid, prompt_id, response_index):
            yield event_chunk
    finally:
        _active_stream_count -= 1


def _error_response(message: str, origin: str, status_code: int = 200) -> StreamingResponse:
    """錯誤串流（同一般串流一樣帶 CORS 標頭——否則瀏覽器只見到 CORS 錯，睇唔到真正原因）。"""
    response_headers = {"Cache-Control": "no-cache"}

    # 判定：有 origin → 回 CORS 標頭
    if origin != "":
        response_headers["Access-Control-Allow-Origin"] = origin
        response_headers["Vary"] = "Origin"

    return StreamingResponse(_error_stream(message), media_type="text/event-stream", headers=response_headers, status_code=status_code)


async def stream_output_for_message(request: Request, project_slug: str, chat_sid: str, prompt_id: str, response_index: int = 0):
    """GET /bridge_sse_for_message：訊息 SSE 串流（唯一入口；identity＝project_slug／chat_sid／prompt_id）。"""
    origin = request.headers.get("origin", "")

    # 判定：slug 唔合法 → 400（防路徑穿越）
    if PROJECT_SLUG_PATTERN.match(project_slug) is None:
        return _error_response("invalid project_slug", origin)

    # 判定：chat_sid 唔合法 → 400（防路徑穿越）
    if PROJECT_SLUG_PATTERN.match(chat_sid) is None:
        return _error_response("invalid chat_sid", origin)

    # 判定：prompt_id 唔合法 → 400（防路徑穿越）
    if PROJECT_SLUG_PATTERN.match(prompt_id) is None:
        return _error_response("invalid prompt_id", origin)

    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403（無 origin＝本機工具，照放行）
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return _error_response("origin not allowed", origin, 403)

    generator = _counted_message_event_generator(request, project_slug, chat_sid, prompt_id, response_index)

    response_headers = {
        "Cache-Control": "no-cache",
        "Connection": "keep-alive",
        "X-Accel-Buffering": "no",
    }

    # 判定：有 origin → 回 CORS 標頭（只准清單內來源）
    if origin != "":
        response_headers["Access-Control-Allow-Origin"] = origin
        response_headers["Vary"] = "Origin"

    return StreamingResponse(
        generator,
        media_type="text/event-stream",
        headers=response_headers,
    )


def _read_poll_list_index(current_dir: Path) -> int:
    """讀 msg_poll_list.jsonl → 最大 response_index（family=response；冇檔回 -1）。"""
    poll_list_path = current_dir / "msg_poll_list.jsonl"

    # 判定：未有 poll_list → 未知
    if poll_list_path.is_file() is False:
        return -1

    max_index = -1

    for poll_list_line in poll_list_path.read_text(encoding="utf-8", errors="replace").splitlines():
        # 判定：空行 → 跳過
        if poll_list_line.strip() == "":
            continue

        try:
            poll_entry = json.loads(poll_list_line)
        except ValueError:
            continue

        # 判定：摘要段（summarize）→ 唔計輪次
        if poll_entry.get("family", "response") != "response":
            continue

        entry_index_raw = poll_entry.get("response_index", -1)

        # 判定：舊記錄（冇 response_index／非數字）→ 跳過
        if isinstance(entry_index_raw, int) is False:
            continue

        entry_index = int(entry_index_raw)

        # 判定：更大輪次 → 記低
        if entry_index > max_index:
            max_index = entry_index

    return max_index


def _read_poll_list_done_keys(current_dir: Path) -> list[tuple[int, str]]:
    """讀 msg_poll_list.jsonl → 已完結（終態）嘅 (response_index, type) 清單（同鍵後行覆蓋前行）。"""
    poll_list_path = current_dir / "msg_poll_list.jsonl"

    # 判定：未有 poll_list → 冇任何完結段
    if poll_list_path.is_file() is False:
        return []

    done_status_by_key: dict[tuple[int, str], str] = {}

    for poll_list_line in poll_list_path.read_text(encoding="utf-8", errors="replace").splitlines():
        # 判定：空行 → 跳過
        if poll_list_line.strip() == "":
            continue

        try:
            poll_entry = json.loads(poll_list_line)
        except ValueError:
            continue

        # 判定：摘要段（summarize）→ 唔計輪次
        if poll_entry.get("family", "response") != "response":
            continue

        entry_index = poll_entry.get("response_index")
        entry_type = str(poll_entry.get("type", ""))

        # 判定：缺 index／type → 跳過
        if isinstance(entry_index, int) is False or entry_type == "":
            continue

        done_status_by_key[(int(entry_index), entry_type)] = str(poll_entry.get("status", ""))

    done_keys = []

    for done_key, done_status in done_status_by_key.items():
        # 判定：未到終態 → 唔算完結
        if done_status not in TERMINAL_STATUSES:
            continue

        done_keys.append(done_key)

    return done_keys


def _collect_turn_done_events(current_dir: Path, turn_done_keys: set[tuple[int, str]]) -> list[dict[str, Any]]:
    """收集未發過嘅段完結事件（turn_done：{kind, text}——該回合該型嘅完整內容）。"""
    turn_done_events: list[dict[str, Any]] = []

    for done_index, done_kind in _read_poll_list_done_keys(current_dir):
        # 判定：已發過 → 跳過
        if (done_index, done_kind) in turn_done_keys:
            continue

        # 判定：只認內文兩型 → 跳過
        if done_kind not in ("thinking", "result"):
            continue

        done_path = _response_file_path(current_dir, done_kind, done_index)
        done_text, _done_offset, _done_truncated = _read_appended(done_path, 0)

        turn_done_keys.add((done_index, done_kind))

        turn_done_events.append({"kind": done_kind, "text": done_text})

    return turn_done_events


def _activity_log_entries(message_dir: Path, offset: int) -> tuple[list[dict[str, Any]], int]:
    """讀 msg_log.txt 由 offset 起的新行 → 活動日誌條目（uid 同 PHP 一致：md5(datetime|event|level|message)）。"""
    log_path = message_dir / "msg_log.txt"

    # 判定：未有 log 檔 → 唔變
    if log_path.is_file() is False:
        return [], offset

    with log_path.open("rb") as log_handle:
        log_handle.seek(offset)
        raw_chunk = log_handle.read()
        new_offset = log_handle.tell()

    log_entries: list[dict[str, Any]] = []

    for raw_line in raw_chunk.splitlines():
        try:
            log_data = json.loads(raw_line.decode("utf-8", errors="replace"))
        except ValueError:
            continue

        log_event = str(log_data.get("event", ""))
        log_level = str(log_data.get("level", "INFO")).lower()
        log_message = str(log_data.get("message", ""))
        log_datetime = str(log_data.get("datetime", ""))

        # 判定：缺 event／message → 跳過（同 PHP 驗證口徑一致）
        if log_event == "" or log_message == "":
            continue

        uid_source = log_datetime + "|" + log_event + "|" + log_level + "|" + log_message

        log_entries.append({
            "activity_uid": hashlib.md5(uid_source.encode("utf-8")).hexdigest(),
            "event": log_event,
            "level": log_level,
            "message": log_message,
            "datetime": log_datetime,
        })

    return log_entries, new_offset


async def _message_event_generator(request: Request, project_slug: str, chat_sid: str, prompt_id: str, response_index: int):
    """訊息串流（新協定）——ready／turn／append／state／done；identity 即時定位目錄。"""
    current_dir = _locate_message_dir(project_slug, chat_sid, prompt_id)

    wait_started_at = time.time()

    # 目錄未建立（排隊中）→ 保持連線等，目錄一出現即照常串流
    while current_dir is None:
        # 判定：前端已斷線 → 直接結束
        if await request.is_disconnected():
            return

        # 判定：等超過單線上限仍未出現 → 送 error 收線
        if time.time() - wait_started_at > STREAM_MAX_SECONDS:
            yield _format_event("error", {"message": "message dir not found"})

            return

        await asyncio.sleep(POLL_INTERVAL_SECONDS)

        current_dir = _locate_message_dir(project_slug, chat_sid, prompt_id)

    stream_meta = _read_meta_json(current_dir)
    stream_message_id = int(stream_meta.get("message_id", 0))

    current_index = int(response_index)
    file_offsets = {"result": 0, "thinking": 0}

    # 工具記錄游標（每輪一個 tools.jsonl——逐行推 tool 事件）
    tool_offset = 0
    last_status = ""

    # 已發過 turn_done 嘅段（(index, kind)）——每段只發一次
    turn_done_keys: set[tuple[int, str]] = set()

    # 首個事件：貼上當刻全文，偏移置檔尾（重連＝續傳，唔重播）
    snapshot_texts: dict[str, str] = {}

    for response_kind in ("thinking", "result"):
        snapshot_path = _response_file_path(current_dir, response_kind, current_index)
        snapshot_text, snapshot_offset, _snapshot_truncated = _read_appended(snapshot_path, 0)

        snapshot_texts[response_kind] = snapshot_text
        file_offsets[response_kind] = snapshot_offset

    ready_logs, log_offset = _activity_log_entries(current_dir, 0)

    yield _format_event(
        "ready",
        {
            "message_id": stream_message_id,
            "index": current_index,
            "status": message.get_msg_status(current_dir),
            "thinking": snapshot_texts["thinking"],
            "result": snapshot_texts["result"],
            "activity_log": ready_logs,
        },
    )

    # 連線時：當前輪及更早嘅已完成段（ready／頁面 HTML 已帶內容）→ 標記已發，唔重播
    for ready_done_key in _read_poll_list_done_keys(current_dir):
        # 判定：唔係當前輪或更早 → 留返 tick 發
        if ready_done_key[0] > current_index:
            continue

        turn_done_keys.add(ready_done_key)

    while True:
        # 判定：前端已斷線 → 直接結束
        if await request.is_disconnected():
            return

        # 判定：現行目錄消失（歸檔搬遷）→ 用 identity 即時重新定位
        if current_dir.is_dir() is False:
            relocated_dir = _locate_message_dir(project_slug, chat_sid, prompt_id)

            # 判定：兩邊都未見（搬遷中）→ 跳過今輪，唔准讀出空狀態
            if relocated_dir is None:
                await asyncio.sleep(POLL_INTERVAL_SECONDS)
                continue

            current_dir = relocated_dir

        new_logs, log_offset = _activity_log_entries(current_dir, log_offset)

        # 判定：有新活動日誌 → log 事件（前端同一行累加）
        if len(new_logs) > 0:
            yield _format_event("log", {"activity_log": new_logs})

        status = message.get_msg_status(current_dir)

        # 判定：狀態有變 → state 事件（＋派工）
        if status != last_status:
            last_status = status
            yield _format_event("state", {"status": status})
            await asyncio.to_thread(_dispatch_on_transition, current_dir)

            # 判定：終態 → 先補齊所有輪次內容，再送 done 收線
            if status in ("completed", "failed", "cancelled"):
                for response_kind in ("thinking", "result"):
                    final_path = _response_file_path(current_dir, response_kind, current_index)
                    final_text, final_offset, _final_truncated = _read_appended(final_path, file_offsets[response_kind])

                    # 判定：有最後增量 → 先送 append
                    if final_text != "":
                        file_offsets[response_kind] = final_offset
                        yield _format_event("append", {"kind": response_kind, "text": final_text})

                # 之後每一輪（> 本輪）→ turn ＋ 該輪全文（重連補齊）
                last_index = _read_poll_list_index(current_dir)

                for next_index in range(current_index + 1, last_index + 1):
                    yield _format_event("turn", {"index": next_index})

                    for response_kind in ("thinking", "result"):
                        next_path = _response_file_path(current_dir, response_kind, next_index)
                        next_text, _next_offset, _next_truncated = _read_appended(next_path, 0)

                        # 判定：該輪有內容 → 送 append
                        if next_text != "":
                            yield _format_event("append", {"kind": response_kind, "text": next_text})

                # 收尾前：工具記錄補推（收尾時 daemon 會補 done 行——唔補推就見唔到狀態）
                tools_path = current_dir / f"msg_response.{current_index}.tools.jsonl"
                tools_text, tool_new_offset, _tools_truncated = _read_appended(tools_path, tool_offset)

                # 判定：有未推嘅工具行 → 逐行推
                if tools_text != "":
                    tool_offset = tool_new_offset

                    for tools_line in tools_text.splitlines():
                        # 判定：空行 → 跳過
                        if tools_line.strip() == "":
                            continue

                        try:
                            tool_record = json.loads(tools_line)
                        except ValueError:
                            continue

                        yield _format_event("tool", tool_record)

                # 收尾前：未發過嘅段完結事件（turn_done）——含最後一輪完整內容
                for turn_done_payload in _collect_turn_done_events(current_dir, turn_done_keys):
                    yield _format_event("turn_done", turn_done_payload)

                yield _format_event("done", {"status": status})

                return

        # 判定：poll_list 出現新輪次 → turn 事件＋新段由零開始
        poll_list_index = _read_poll_list_index(current_dir)

        if poll_list_index > current_index:
            current_index = poll_list_index
            file_offsets = {"result": 0, "thinking": 0}
            tool_offset = 0
            yield _format_event("turn", {"index": current_index})

        for response_kind in ("thinking", "result"):
            file_path = _response_file_path(current_dir, response_kind, current_index)
            appended_text, new_offset, truncated = _read_appended(file_path, file_offsets[response_kind])

            # 判定：檔案被截短 → 重設偏移，等下一拍重讀
            if truncated is True:
                file_offsets[response_kind] = 0
                continue

            # 判定：有新內容 → append 事件
            if appended_text != "":
                file_offsets[response_kind] = new_offset
                yield _format_event("append", {"kind": response_kind, "text": appended_text})

        # 工具記錄：讀本輪 tools.jsonl 新行 → tool 事件（逐條推）
        tools_path = current_dir / f"msg_response.{current_index}.tools.jsonl"
        tools_text, tool_new_offset, _tools_truncated = _read_appended(tools_path, tool_offset)

        # 判定：有新行 → 逐行推
        if tools_text != "":
            tool_offset = tool_new_offset

            for tools_line in tools_text.splitlines():
                # 判定：空行 → 跳過
                if tools_line.strip() == "":
                    continue

                try:
                    tool_record = json.loads(tools_line)
                except ValueError:
                    continue

                yield _format_event("tool", tool_record)

        # 段完結 → turn_done（該回合該型完整內容；每段只發一次）
        for turn_done_payload in _collect_turn_done_events(current_dir, turn_done_keys):
            yield _format_event("turn_done", turn_done_payload)

        await asyncio.sleep(POLL_INTERVAL_SECONDS)


def _build_message_dir(root: Path, project_slug: str, chat_sid: str, prompt_id: str) -> Path:
    """由 identity 砌訊息目錄路徑（唔查存在——存在判斷交呼叫端）。"""
    return root / project_slug / chat_sid / prompt_id


def _locate_message_dir(project_slug: str, chat_sid: str, prompt_id: str) -> Path | None:
    """即時定位訊息目錄：先 polling、後 archive；兩邊都冇 → None（唔准砌假路徑）。"""
    polling_dir = _build_message_dir(MESSAGES_POLLING_DIR, project_slug, chat_sid, prompt_id)

    # 判定：仲喺 polling → 回 polling
    if polling_dir.is_dir():
        return polling_dir

    archive_dir = _build_message_dir(MESSAGES_ARCHIVE_DIR, project_slug, chat_sid, prompt_id)

    # 判定：已歸檔 → 回 archive
    if archive_dir.is_dir():
        return archive_dir

    return None


def _read_meta_json(message_dir: Path) -> dict[str, Any]:
    """讀箱內 meta.json（讀唔到／非 dict → 空 dict）。"""
    meta_path = message_dir / "meta.json"

    # 判定：冇 meta.json → 空
    if meta_path.is_file() is False:
        return {}

    try:
        meta_data = json.loads(meta_path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return {}

    # 判定：唔係 dict → 空
    if isinstance(meta_data, dict) is False:
        return {}

    return meta_data


def _dispatch_on_transition(message_dir: Path) -> None:
    """SSE 狀態轉換 → 依箱內 meta.status 派工（pending → 抽取器；polling → agent；其餘唔做）。"""
    meta_data = _read_meta_json(message_dir)

    # 判定：meta 讀唔到 → 唔做
    if len(meta_data) == 0:
        return

    stage_status    = str(meta_data.get("status", ""))
    message_id      = int(str(meta_data.get("message_id", 0)))
    chat_id         = int(str(meta_data.get("chat_id", 0)))
    agent           = str(meta_data.get("agent", ""))

    # 判定：等抽取 → 通知抽取器（箱內標記擋重複）
    if stage_status == "pending":
        notify_marker = message_dir / "notify.image_extractor"

        # 判定：已通知過 → 唔重複
        if notify_marker.exists() is True:
            return

        identity: dict[str, Any] = {}
        identity["message_id"] = message_id
        identity["chat_id"] = chat_id
        identity["chat_sid"] = str(meta_data.get("chat_sid", ""))
        identity["project_slug"] = str(meta_data.get("project_slug", ""))

        result = server_image_extract.start(identity)

        # 判定：開工成功 → 落標記
        if result.get("ok") is True:
            _ = notify_marker.touch()

        return

    # 判定：等開工 → 通知 agent（箱內標記擋重複）
    if stage_status == "polling":
        notify_marker = message_dir / "notify.agent"

        # 判定：已通知過 → 唔重複
        if notify_marker.exists() is True:
            return

        payload: dict[str, Any] = {}
        payload["chat_id"]      = chat_id
        payload["message_id"]   = message_id
        payload["agent"]        = agent

        result = server_daemon.add_message(payload)

        # 判定：喚醒成功 → 落標記
        if result.get("ok") is True:
            _ = notify_marker.touch()


def _response_file_path(message_dir: Path, response_kind: str, response_index: int) -> Path:
    """回應檔路徑（msg_response.{index}.result.txt／thinking.txt——agent 檔案契約）。"""
    file_name = "msg_response." + str(int(response_index)) + "." + response_kind + ".txt"
    return message_dir / file_name


def _read_appended(file_path: Path, offset: int) -> tuple[str, int, bool]:
    """由 offset 讀新增內容（回 (文字, 新偏移, 是否被截短)）。"""
    # 判定：檔案唔存在 → 冇新內容
    if file_path.is_file() is False:
        return "", offset, False

    try:
        file_size = file_path.stat().st_size
    except OSError:
        return "", offset, False

    # 判定：檔案比偏移短 → 被截短（重寫過）
    if file_size < offset:
        return "", 0, True

    # 判定：冇新增 → 冇新內容
    if file_size == offset:
        return "", offset, False

    try:
        with file_path.open("rb") as file_handle:
            _ = file_handle.seek(offset)
            appended_bytes = file_handle.read()
    except OSError:
        return "", offset, False

    return appended_bytes.decode("utf-8", "replace"), file_size, False


def _format_event(event_name: str, payload: dict[str, Any]) -> str:
    """砌 SSE 幀（event ＋ 單行 JSON data）。"""
    payload_json = json.dumps(payload, ensure_ascii=False)
    return "event: " + event_name + "\ndata: " + payload_json + "\n\n"


def _error_stream(error_message: str):
    """錯誤串流（送一個 error 事件即收）。"""
    yield _format_event("error", {"message": error_message})

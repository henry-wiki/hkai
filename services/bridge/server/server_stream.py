#!/usr/bin/env python3
"""
server_stream.py — SSE 串流端點（輸出監控）。

職責：
- build_router()：建立路由器（stream 路徑＝固定契約 "/stream"——Apache 反代同名）
- stream_output()：GET /stream 端點（tail 訊息目錄檔案，push SSE 事件）
- _resolve_message_dirs()：解析 polling／archive 兩個目錄
- message.get_msg_status()：讀 msg_status.txt 最後一行（bridge/message 提供）
- _read_appended()：由指定位元偏移讀新增內容（回 (新內容, 新偏移)）
- _format_event()：砌 SSE 幀（event ＋ JSON data）

設計：
1. 只讀檔案契約（msg_status.txt／msg_response.{n}.result.txt／msg_response.{n}.thinking.txt）
2. 唔 import 任何 agent 模組——aider_daemon 同 goose_daemon 寫同一批檔，一套就够
3. 訊息完成後 PHP 會將目錄搬去 archive——偵測 polling 消失即轉讀 archive 並收尾
"""

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false
# （狀態／job JSON 動態——Unknown/Any 警告一律關，其餘照檢）

import asyncio
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
    """建立串流路由器（stream 路徑＝固定契約 "/stream"）。"""
    router = APIRouter()
    router.add_api_route("/stream", stream_output, methods=["GET"])
    return router


_active_stream_count = 0


def get_active_stream_count() -> int:
    """回傳現時開住嘅 SSE 串流數（bridge 閒置退出用）。"""
    return _active_stream_count


async def _counted_event_generator(request: Request, message_dirs: dict[str, Path], response_index: int, message_id: int):
    """包一層：串流開／關時加減計數（閒置退出唔可以殺死進行中嘅串流）。"""
    global _active_stream_count
    _active_stream_count += 1

    try:
        async for event_chunk in _event_generator(request, message_dirs, response_index, message_id):
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


async def stream_output(request: Request, project_slug: str, message_id: int, response_index: int = 0):
    """GET /stream：tail 指定訊息目錄，逐段 push 內容（SSE）。"""
    origin = request.headers.get("origin", "")

    # 判定：slug 唔合法 → 400（防路徑穿越）
    if PROJECT_SLUG_PATTERN.match(project_slug) is None:
        return _error_response("invalid project_slug", origin)

    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403（無 origin＝本機工具，照放行）
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return _error_response("origin not allowed", origin, 403)

    message_dirs = _resolve_message_dirs(project_slug, message_id)

    # 判定：兩個目錄都唔存在 → 404 串流（前端收到 error 事件即停）
    if message_dirs["polling"].is_dir() is False and message_dirs["archive"].is_dir() is False:
        return _error_response("message dir not found", origin)

    generator = _counted_event_generator(request, message_dirs, response_index, int(message_id))

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


def _find_message_dir(root: Path, project_slug: str, message_id: int) -> Path:
    """喺 <root>/<slug>/<chat_sid>/<prompt_id>/ 找 message_id 相符嘅箱（目錄名＝prompt_id，已唔再係 message_id）。"""
    project_root = root / project_slug

    # 判定：專案目錄唔存在 → 回舊式路徑（呼叫端當唔存在）
    if project_root.is_dir() is False:
        return root / project_slug / str(int(message_id))

    # 逐個三層 meta.json 對 message_id（箱內 meta 係唯一真相）
    for meta_path in project_root.glob("*/*/meta.json"):
        try:
            meta_data = json.loads(meta_path.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            continue

        # 判定：message_id 相符 → 回該箱目錄
        if int(meta_data.get("message_id", 0)) == int(message_id):
            return meta_path.parent

    # 搵唔到 → 回舊式兩層路徑（舊訊息 fallback——正常唔會行）
    return root / project_slug / str(int(message_id))


def _resolve_message_dirs(project_slug: str, message_id: int) -> dict[str, Path]:
    """解析 polling／archive 兩個訊息目錄（三層：project_slug／chat_sid／prompt_id）。"""
    message_dirs: dict[str, Path] = {}
    message_dirs["polling"] = _find_message_dir(MESSAGES_POLLING_DIR, project_slug, message_id)
    message_dirs["archive"] = _find_message_dir(MESSAGES_ARCHIVE_DIR, project_slug, message_id)
    return message_dirs


async def _event_generator(request: Request, message_dirs: dict[str, Path], response_index: int, message_id: int):
    """SSE 主迴圈：送快照 → 逐段送 delta → 終態收尾。"""
    current_dir = message_dirs["polling"]
    offsets = {"result": 0, "thinking": 0}
    last_status = ""
    started_at = time.monotonic()
    last_beat_at = started_at

    yield _format_event(
        "snapshot",
        {
            "message_id": message_id,
            "status": message.get_msg_status(current_dir),
            "result": "",
            "thinking": "",
            "extract": message.extract_progress(current_dir),
        },
    )

    last_extract_json = ""

    while True:
        # 判定：前端已斷線 → 直接結束（唔再讀檔）
        if await request.is_disconnected():
            return

        # 判定：polling 目錄消失（已被歸檔）→ 轉讀 archive
        if current_dir.is_dir() is False and message_dirs["archive"].is_dir():
            current_dir = message_dirs["archive"]

        status = message.get_msg_status(current_dir)

        # 判定：狀態有變 → 送 status 事件＋依轉換派工（stream 驅動）
        if status != last_status:
            last_status = status
            yield _format_event("status", {"status": status})
            await asyncio.to_thread(_dispatch_on_transition, current_dir)

        extract_phase = message.extract_progress(current_dir)
        extract_json = json.dumps(extract_phase, ensure_ascii=False, sort_keys=True)

        # 判定：抽取進度有變 → 送 phase 事件（前端 activity log 用）
        if extract_json != last_extract_json:
            last_extract_json = extract_json
            yield _format_event("phase", {"status": status, "extract": extract_phase})

        for response_kind in ("result", "thinking"):
            file_path = _response_file_path(current_dir, response_kind, response_index)
            appended_text, new_offset, truncated = _read_appended(file_path, offsets[response_kind])

            # 判定：檔案被截短（重寫）→ 重置偏移並通知前端重載
            if truncated is True:
                offsets[response_kind] = 0
                yield _format_event("reset", {"kind": response_kind})
                continue

            # 判定：有新內容 → 送 delta 事件
            if appended_text != "":
                offsets[response_kind] = new_offset
                yield _format_event("delta", {"kind": response_kind, "text": appended_text})

        # 判定：已到終態 → 收尾（再讀一輪確保尾段已送）
        if status in TERMINAL_STATUSES and current_dir == message_dirs["archive"]:
            yield _format_event("done", {"status": status})
            return

        # 判定：polling 已終態（未歸檔）→ 收尾
        if status in TERMINAL_STATUSES and current_dir == message_dirs["polling"]:
            yield _format_event("done", {"status": status})
            return

        # 判定：接近上限 → 收尾（前端自動重連）
        if time.monotonic() - started_at > STREAM_MAX_SECONDS:
            yield _format_event("timeout", {"status": status})
            return

        # 判定：距上次心跳够久 → 送註釋幀（保持連線）
        if time.monotonic() - last_beat_at > HEARTBEAT_INTERVAL_SECONDS:
            last_beat_at = time.monotonic()
            yield ": ping\n\n"

        await asyncio.sleep(POLL_INTERVAL_SECONDS)


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

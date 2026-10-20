#!/usr/bin/env python3
"""
server_stream.py — SSE 串流端點（輸出監控）。

職責：
- build_router()：建立路由器（stream 路徑＝固定契約 "/stream"——Apache 反代同名）
- stream_output()：GET /stream 端點（tail 訊息目錄檔案，push SSE 事件）
- _resolve_message_dirs()：解析 polling／archive 兩個目錄
- _read_status()：讀 msg_status.txt 最後一行
- _read_appended()：由指定位元偏移讀新增內容（回 (新內容, 新偏移)）
- _format_event()：砌 SSE 幀（event ＋ JSON data）

設計：
1. 只讀檔案契約（msg_status.txt／msg_response.{n}.result.txt／msg_response.{n}.thinking.txt）
2. 唔 import 任何 engine 模組——aider_daemon 同 goose_daemon 寫同一批檔，一套就够
3. 訊息完成後 PHP 會將目錄搬去 archive——偵測 polling 消失即轉讀 archive 並收尾
"""

import asyncio
import json
import re
import time
from pathlib import Path

from fastapi import APIRouter, Request
from fastapi.responses import StreamingResponse

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


def build_router() -> APIRouter:
    """建立串流路由器（stream 路徑＝固定契約 "/stream"）。"""
    router = APIRouter()
    router.add_api_route("/stream", stream_output, methods=["GET"])
    return router


async def stream_output(request: Request, project_slug: str, message_id: int, response_index: int = 0):
    """GET /stream：tail 指定訊息目錄，逐段 push 內容（SSE）。"""
    # 判定：slug 唔合法 → 400（防路徑穿越）
    if PROJECT_SLUG_PATTERN.match(project_slug) is None:
        return StreamingResponse(_error_stream("invalid project_slug"), media_type="text/event-stream")

    config = request.app.state.config
    origin = request.headers.get("origin", "")
    allowed_origins = _read_origin_allowlist(config)

    # 判定：origin 唔喺允許清單 → 403（無 origin＝本機工具，照放行）
    if origin != "" and _origin_is_allowed(origin, allowed_origins) is False:
        return StreamingResponse(_error_stream("origin not allowed"), media_type="text/event-stream", status_code=403)

    message_dirs = _resolve_message_dirs(config, project_slug, message_id)

    # 判定：兩個目錄都唔存在 → 404 串流（前端收到 error 事件即停）
    if message_dirs["polling"].is_dir() is False and message_dirs["archive"].is_dir() is False:
        return StreamingResponse(_error_stream("message dir not found"), media_type="text/event-stream")

    generator = _event_generator(request, message_dirs, response_index, int(message_id))

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


def _resolve_message_dirs(config: dict, project_slug: str, message_id: int) -> dict:
    """解析 polling／archive 兩個訊息目錄（值全部由 runtime.json 帶）。"""
    storage_dir = Path(config["storage_dir"])
    folders = config.get("folders", {})

    polling_root = storage_dir / folders.get("messages", "messages/polling")
    archive_root = storage_dir / folders.get("archive", "messages/archive")

    message_dirs = {}
    message_dirs["polling"] = polling_root / project_slug / str(int(message_id))
    message_dirs["archive"] = archive_root / project_slug / str(int(message_id))
    return message_dirs


def _read_origin_allowlist(config: dict) -> list:
    """讀 CORS 允許清單（hkai_config.json stream.allowed_origins——改檔即生效，免重啟）。"""
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


def _origin_is_allowed(origin: str, allowed_origins: list) -> bool:
    """判斷 origin 是否獲准（支援萬用埠：http://127.0.0.1:*）。"""
    for allowed_origin in allowed_origins:
        # 判定：完全相符 → 准
        if allowed_origin == origin:
            return True

        # 判定：通配埠（結尾 :*）→ 前綴相符即准
        if allowed_origin.endswith(":*") and origin.startswith(allowed_origin[:-1]):
            return True

    return False


async def _event_generator(request: Request, message_dirs: dict, response_index: int, message_id: int):
    """SSE 主迴圈：送快照 → 逐段送 delta → 終態收尾。"""
    current_dir = message_dirs["polling"]
    offsets = {"result": 0, "thinking": 0}
    last_status = ""
    started_at = time.monotonic()
    last_beat_at = started_at

    yield _format_event("snapshot", {"message_id": message_id, "status": _read_status(current_dir), "result": "", "thinking": ""})

    while True:
        # 判定：前端已斷線 → 直接結束（唔再讀檔）
        if await request.is_disconnected():
            return

        # 判定：polling 目錄消失（已被歸檔）→ 轉讀 archive
        if current_dir.is_dir() is False and message_dirs["archive"].is_dir():
            current_dir = message_dirs["archive"]

        status = _read_status(current_dir)

        # 判定：狀態有變 → 送 status 事件
        if status != last_status:
            last_status = status
            yield _format_event("status", {"status": status})

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


def _response_file_path(message_dir: Path, response_kind: str, response_index: int) -> Path:
    """回應檔路徑（msg_response.{index}.result.txt／thinking.txt——engine 檔案契約）。"""
    file_name = "msg_response." + str(int(response_index)) + "." + response_kind + ".txt"
    return message_dir / file_name


def _read_status(message_dir: Path) -> str:
    """讀 msg_status.txt 最後一行（冇檔／空白＝空字串）。"""
    status_path = message_dir / "msg_status.txt"

    # 判定：狀態檔唔存在 → 回空
    if status_path.is_file() is False:
        return ""

    try:
        status_text = status_path.read_text(encoding="utf-8", errors="replace")
    except OSError:
        return ""

    status_lines = [status_line.strip() for status_line in status_text.splitlines() if status_line.strip() != ""]

    # 判定：冇任何狀態行 → 回空
    if len(status_lines) == 0:
        return ""

    return status_lines[-1]


def _read_appended(file_path: Path, offset: int) -> tuple:
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
            file_handle.seek(offset)
            appended_bytes = file_handle.read()
    except OSError:
        return "", offset, False

    return appended_bytes.decode("utf-8", "replace"), file_size, False


def _format_event(event_name: str, payload: dict) -> str:
    """砌 SSE 幀（event ＋ 單行 JSON data）。"""
    payload_json = json.dumps(payload, ensure_ascii=False)
    return "event: " + event_name + "\ndata: " + payload_json + "\n\n"


def _error_stream(error_message: str):
    """錯誤串流（送一個 error 事件即收）。"""
    yield _format_event("error", {"message": error_message})

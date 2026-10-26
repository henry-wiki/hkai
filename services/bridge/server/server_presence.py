# 職責：頁面在線（presence）——每個瀏覽器頁面開一條 SSE，bridge 以連線數判斷「仲有冇人」。
# 上游：server.py 註冊 router；前端 jsmods/sc_stream/presence/presence.js 開 EventSource。
# 下游：heartbeat 讀 presence.age_sec()（有連線持續刷新 → 唔准閒置自殺）。
import asyncio
import json

from fastapi import APIRouter, Request
from fastapi.responses import PlainTextResponse, Response, StreamingResponse

from ..context import PRESENCE_MAX_CONNECTIONS, PRESENCE_PING_SEC, get_datetime
from ..runtime import presence
from .server_cors import origin_is_allowed, read_origin_allowlist

# 現時在線頁面數（每條 SSE 連線 +1／斷線 -1）
_active_connection_count = 0

router = APIRouter()


def active_connection_count() -> int:
    """現時在線連線數（每條 presence SSE＝1）。"""
    return _active_connection_count


async def _presence_stream():
    """SSE 串流：連線期間每 PRESENCE_PING_SEC 秒送一次 ping 事件；客戶端斷線即結束。"""
    global _active_connection_count

    _active_connection_count += 1

    # 連線開始＝在線（更新 last_presence mtime）
    presence.touch()

    try:
        while True:
            # 每次 ping 都刷新在線標記（連線存活期間 mtime 唔會過期）
            presence.touch()

            # ping 事件（type＋時間；DevTools EventStream 睇得到，前端可監聽）
            ping_payload = {"type": "ping", "datetime": get_datetime()}
            ping_json = json.dumps(ping_payload, ensure_ascii=False)
            ping_frame = "event: ping" + chr(10) + "data: " + ping_json + chr(10) + chr(10)

            yield ping_frame

            await asyncio.sleep(PRESENCE_PING_SEC)
    finally:
        # 客戶端斷線（uvicorn 取消 task）／正常結束 → 在線數減一
        _active_connection_count -= 1


@router.get("/presence")
async def presence_stream(request: Request):
    """頁面在線 SSE（前端 EventSource 直連；唔經 PHP）。"""
    origin = request.headers.get("origin", "")
    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403（無 origin＝本機工具，照放行）
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return PlainTextResponse("origin not allowed", status_code=403)

    response_headers = {
        "Cache-Control": "no-cache",
        "Connection": "keep-alive",
        "X-Accel-Buffering": "no",
    }

    # 判定：有 origin → 回 CORS 標頭（只准清單內來源）
    if origin != "":
        response_headers["Access-Control-Allow-Origin"] = origin
        response_headers["Vary"] = "Origin"

    # 判定：已達連線上限 → 503（前端當普通斷線，3 秒後重連）
    if active_connection_count() >= PRESENCE_MAX_CONNECTIONS:
        return PlainTextResponse("too many connections", status_code=503, headers=response_headers)

    return StreamingResponse(
        _presence_stream(),
        media_type="text/event-stream",
        headers=response_headers,
    )


@router.options("/presence")
def presence_options(request: Request):
    """CORS 預檢（fetch 帶 cache-control／pragma 等非簡單標頭時，瀏覽器會先問）。"""
    origin = request.headers.get("origin", "")
    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403（無 origin＝本機工具，照放行）
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return Response(status_code=403)

    response_headers = {
        "Access-Control-Allow-Methods": "GET, OPTIONS",
        "Access-Control-Allow-Headers": request.headers.get("access-control-request-headers", ""),
        "Access-Control-Max-Age": "600",
    }

    # 判定：有 origin → 回 CORS 標頭（只准清單內來源）
    if origin != "":
        response_headers["Access-Control-Allow-Origin"] = origin
        response_headers["Vary"] = "Origin"

    return Response(status_code=204, headers=response_headers)

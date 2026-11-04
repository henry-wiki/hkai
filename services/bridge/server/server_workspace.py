# 職責：工作區工具狀態（workspace）——每個瀏覽器頁面一條 SSE，推「當前專案」嘅工具狀態（首項＝git）。
# 上游：server.py 註冊 router；前端 jsmods/sc_stream/workspace/workspace_stream.js 開 EventSource。
# 下游：sqlite（projects 表查 project_path）、diagnostics.git.git_status（同 PHP 側同一份實作）。
import asyncio
import json
import sqlite3
import time
from pathlib import Path

from fastapi import APIRouter, Request
from fastapi.responses import PlainTextResponse, StreamingResponse

from diagnostics.git.git_status import git_status_class
from ..context import LOCALDB_DIR
from .server_cors import origin_is_allowed, read_origin_allowlist

# 檢查間隔（秒）
WORKSPACE_TICK_SECONDS = 3.0

# ping 間隔（秒——防中間代理斷閒置連線）
WORKSPACE_PING_SECONDS = 20.0

# 單條連線上限（秒——到頂即收線，前端自動重連）
WORKSPACE_MAX_SECONDS = 280.0

router = APIRouter()

# git 診斷實例（同 PHP 側同一份實作）
_GIT_STATUS = git_status_class()


def _read_project_path(project_slug: str) -> str:
    """由 sqlite projects 表查專案路徑（讀不到／查不到 → 空字串）。"""
    # 判定：未帶專案 → 唔查
    if project_slug == "":
        return ""

    db_path = Path(LOCALDB_DIR) / "hkai_chat_data.sqlite"

    # 判定：DB 檔唔存在 → 空
    if db_path.is_file() is False:
        return ""

    try:
        connection = sqlite3.connect("file:" + db_path.as_posix() + "?mode=ro", uri=True, timeout=2)
        row = connection.execute(
            "SELECT project_path FROM projects WHERE project_slug = ?",
            (project_slug,),
        ).fetchone()
        connection.close()
    except sqlite3.Error:
        return ""

    # 判定：查唔到 → 空
    if row is None:
        return ""

    return str(row[0] or "")


def _build_git_entry(project_path: str) -> dict:
    """砌 git 狀態——原樣用 diagnostics git_status 結果（同 PHP /git_status/ 完全同一份形）。"""
    # 判定：冇專案路徑 → 空結果
    if project_path == "":
        return {"ok": False, "is_repo": False}

    return _GIT_STATUS.get_status(project_path)


def _format_event(event_name: str, payload: dict) -> str:
    """砌具名 SSE 幀（event＋data 兩行）。"""
    payload_json = json.dumps(payload, ensure_ascii=False)

    return "event: " + event_name + chr(10) + "data: " + payload_json + chr(10) + chr(10)


def _format_manifest(payload: dict) -> str:
    """砌無名 SSE 幀（連線第一幀＝事件名單，前端照名單自行掛 listener）。"""
    payload_json = json.dumps(payload, ensure_ascii=False)

    return "data: " + payload_json + chr(10) + chr(10)


async def _workspace_stream(project_slug: str, project_path: str):
    """SSE 串流：每 tick 檢查工具狀態，有變才推；期間定期 ping；到上限／斷線即收。"""
    started_at = time.monotonic()
    last_ping_at = time.monotonic()
    last_git_json = ""
    tick_seconds = WORKSPACE_TICK_SECONDS

    # 第一幀：事件名單（無名）——前端照名單自行掛 listener，前端唔寫死任何事件名
    yield _format_manifest({"events": ["git", "ping"]})

    while True:
        tick_started_at = time.monotonic()

        git_entry = await asyncio.to_thread(_build_git_entry, project_path)

        git_payload = {"project_slug": project_slug, "entry": git_entry}
        git_json = json.dumps(git_payload, ensure_ascii=False, sort_keys=True)

        # 判定：狀態有變（首 tick 一定推）→ 送 git 事件
        if git_json != last_git_json:
            last_git_json = git_json

            yield _format_event("git", git_payload)

        # 判定：夠鐘 → ping 保活
        if time.monotonic() - last_ping_at >= WORKSPACE_PING_SECONDS:
            last_ping_at = time.monotonic()

            yield _format_event("ping", {"type": "ping"})

        # 判定：到連線上限 → 收線（前端自動重連）
        if time.monotonic() - started_at >= WORKSPACE_MAX_SECONDS:
            return

        tick_duration = time.monotonic() - tick_started_at

        # 判定：git 用時過長 → 拉長間隔（大 repo 唔好每秒跑）
        tick_seconds = max(WORKSPACE_TICK_SECONDS, tick_duration * 3)

        await asyncio.sleep(tick_seconds)


@router.get("/workspace_stream")
async def workspace_stream(request: Request):
    """工作區工具狀態 SSE（前端 EventSource 直連；只帶位置提示，唔用作資料篩選）。"""
    origin = request.headers.get("origin", "")
    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403（無 origin＝本機工具，照放行）
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return PlainTextResponse("origin not allowed", status_code=403)

    project_slug = request.query_params.get("project_slug", "")

    project_path = _read_project_path(project_slug)

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
        _workspace_stream(project_slug, project_path),
        media_type="text/event-stream",
        headers=response_headers,
    )


@router.options("/workspace_stream")
def workspace_options(request: Request):
    """CORS 預檢。"""
    origin = request.headers.get("origin", "")
    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return PlainTextResponse("origin not allowed", status_code=403)

    response_headers = {
        "Access-Control-Allow-Methods": "GET, OPTIONS",
        "Access-Control-Allow-Headers": request.headers.get("access-control-request-headers", ""),
        "Access-Control-Max-Age": "600",
    }

    # 判定：有 origin → 回 CORS 標頭
    if origin != "":
        response_headers["Access-Control-Allow-Origin"] = origin
        response_headers["Vary"] = "Origin"

    return PlainTextResponse("", status_code=204, headers=response_headers)

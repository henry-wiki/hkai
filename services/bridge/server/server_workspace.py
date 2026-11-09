# 職責：工作區工具狀態（workspace）——每個瀏覽器頁面一條 SSE，推「當前專案」嘅工具狀態（首項＝git）。
# 上游：server.py 註冊 router；前端 jsmods/sc_stream/workspace/workspace_stream.js 開 EventSource。
# 下游：sqlite（projects 表查 project_path）、diagnostics.git.git_status（同 PHP 側同一份實作）。
# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false
# （git 狀態／SQLite row 動態——Unknown/Any 警告一律關，其餘照檢）

import asyncio
import json
import sqlite3
import time
import uuid
from collections.abc import Mapping
from pathlib import Path

from diagnostics.git.git_status import git_status_class
from fastapi import APIRouter, Request
from fastapi.responses import PlainTextResponse, StreamingResponse

from ..context import LOCALDB_DIR, MESSAGES_POLLING_DIR
from .server_cors import origin_is_allowed, read_origin_allowlist

# 檢查間隔（秒——2026-11-10 用戶指示改 1 秒：變化最快 1 秒內反映）
WORKSPACE_TICK_SECONDS = 1.0

# 單條連線上限（秒——到頂即收線，前端自動重連）
WORKSPACE_MAX_SECONDS = 280.0

# 快照重推節奏（秒——未收到 ACK 才重推）
SNAPSHOT_RETRY_INTERVAL_SECONDS = 3.0

# 快照重推上限（次——1 分鐘後放棄；下次連線會再收到最新）
SNAPSHOT_MAX_RETRY_COUNT = 20

# ACK 登記（key＝"<stream_id>|<event>" → 已確認版本；generator 每拍讀）
_ACK_VERSION: dict[str, int] = {}

# UI 現況回報（key＝"<stream_id>|<event>|<version>" → 前端收集嘅畫面現況；收斂比對用）
_ACK_OBSERVED: dict[str, dict[str, object]] = {}

# 收斂比對欄位（每事件只比「前端可以原值讀返」嘅欄位——避免格式差異造成假唔一致）
_MISMATCH_FIELD_MAP: dict[str, tuple[str, ...]] = {
    "project": ("updated_at",),
    "chat": ("updated_at", "msg_count"),
    "message": ("status",),
}

# 同一個「唔一致簽名」連續幾次都一樣 → 當前端修唔到，停止重推（唔等 20 次）
SNAPSHOT_MISMATCH_REPEAT_LIMIT = 3

# 唔一致重複登記（key＝"<stream_id>|<event>" → (簽名, 次數)）
_MISMATCH_REPEAT: dict[str, tuple[str, int]] = {}

# 容許「文件有、畫面冇」（唔當未收斂）嘅事件：
#   message——訊息窗係 windowed（只渲染最近一批），舊訊息本身就唔會出現喺 DOM
_MISMATCH_ALLOW_MISSING: set[str] = {"message"}

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


def _build_git_entry(project_path: str) -> Mapping[str, object]:
    """砌 git 狀態——原樣用 diagnostics git_status 結果（同 PHP /git_status/ 完全同一份形）。"""
    # 判定：冇專案路徑 → 空結果
    if project_path == "":
        return {"ok": False, "is_repo": False}

    return _GIT_STATUS.get_status(project_path)



def _read_polling_counts() -> tuple[dict[str, dict[str, int]], dict[str, dict[str, int]]]:
    """掃全站 polling 目錄 → (每專案 {slug: {polling_counts}}, 每對話 {chat_sid: {polling_counts}})。"""
    project_count_map: dict[str, dict[str, int]] = {}
    chat_count_map: dict[str, dict[str, int]] = {}

    polling_root = Path(MESSAGES_POLLING_DIR)

    # 判定：polling 根目錄唔存在 → 當冇
    if polling_root.is_dir() is False:
        return project_count_map, chat_count_map

    for project_dir in polling_root.iterdir():
        # 判定：非目錄 → 跳過
        if project_dir.is_dir() is False:
            continue

        project_count = 0

        for chat_dir in project_dir.iterdir():
            # 判定：非目錄 → 跳過
            if chat_dir.is_dir() is False:
                continue

            chat_prompt_count = 0

            for prompt_dir in chat_dir.iterdir():
                # 判定：非目錄 → 跳過
                if prompt_dir.is_dir() is False:
                    continue

                chat_prompt_count = chat_prompt_count + 1

            # 判定：該對話有 prompt 仍處理中 → 記低
            if chat_prompt_count > 0:
                chat_entry: dict[str, int] = {}
                chat_entry["polling_counts"] = chat_prompt_count

                chat_count_map[chat_dir.name] = chat_entry
                project_count = project_count + chat_prompt_count

        # 判定：該專案有 prompt 仍處理中 → 記低
        if project_count > 0:
            project_entry: dict[str, int] = {}
            project_entry["polling_counts"] = project_count

            project_count_map[project_dir.name] = project_entry

    return project_count_map, chat_count_map


def _build_polling_payload() -> Mapping[str, object]:
    """砌 polling 事件內容（全站清單：projects＋chats，各帶 polling_counts）。"""
    project_count_map, chat_count_map = _read_polling_counts()

    polling_payload: dict[str, object] = {}
    polling_payload["projects"] = project_count_map
    polling_payload["chats"] = chat_count_map

    return polling_payload


def _ack_key(stream_id: str, event_name: str) -> str:
    """ACK 登記 key（每條連線每個事件一個版本）。"""
    return stream_id + "|" + event_name


def _read_acked_version(stream_id: str, event_name: str) -> int:
    """讀前端已確認版本（冇＝0）。"""
    return int(_ACK_VERSION.get(_ack_key(stream_id, event_name), 0))


def _observed_key(stream_id: str, event_name: str, version: int) -> str:
    """UI 現況回報 key（每條連線、每個事件、每個版本一份）。"""
    return stream_id + "|" + event_name + "|" + str(version)


def _read_observed(stream_id: str, event_name: str, version: int) -> dict[str, object] | None:
    """讀前端回報嘅 UI 現況（冇＝未回報）。"""
    return _ACK_OBSERVED.get(_observed_key(stream_id, event_name, version))


def _write_observed(stream_id: str, event_name: str, version: int, observed_items: dict[str, object]) -> None:
    """記低前端回報嘅 UI 現況（只保留最近 500 份，超過清舊）。"""
    # 判定：登記過多（大量重載）→ 清一次
    if len(_ACK_OBSERVED) > 500:
        _ACK_OBSERVED.clear()

    _ACK_OBSERVED[_observed_key(stream_id, event_name, version)] = observed_items


def _find_mismatch(event_name: str, expected_snapshot: Mapping[str, object], observed_items: Mapping[str, object], context_project_slug: str = "", context_is_archive: bool = False) -> list[str]:
    """比對「後台文件」同「前端 UI 現況」→ 回傳對唔上嘅 key 清單（空＝收斂）。

    比較範圍（2026-11-09 用戶拍板）：只比文件列出嘅 key；UI 多出嘅舊 key 唔阻收斂。
    """
    mismatch_key_list: list[str] = []

    # 兩邊輸入統一轉做 dict（後面先可以安全取值）
    expected_map: dict[str, object] = {}

    for expected_key in expected_snapshot:
        expected_map[str(expected_key)] = expected_snapshot[expected_key]

    observed_map: dict[str, object] = {}

    for observed_key in observed_items:
        observed_map[str(observed_key)] = observed_items[observed_key]

    # 判定：polling 唔參與收斂比對——快照係全站（含其他專案嘅對話），而畫面只列當前專案嘅卡，
    #       跨專案 presence 比對必然假唔一致（會造成重推風暴）；polling 由前端每次套用即回 ACK
    if event_name == "polling":
        return mismatch_key_list

    # 判定：其他事件逐 key 比指定欄位
    field_name_list = _MISMATCH_FIELD_MAP.get(event_name, ())

    for snapshot_key in expected_map:
        snapshot_entry = expected_map[snapshot_key]

        # 判定：文件 entry 唔係 dict → 跳過
        if not isinstance(snapshot_entry, dict):
            continue

        # 判定：chat 快照係全站；前端畫面只有「當前專案＋當前語境」嘅對話 → 其餘唔參與比對
        if event_name == "chat":
            snapshot_project_slug = str(snapshot_entry.get("project_slug", ""))

            # 判定：唔屬當前專案 → 跳過
            if context_project_slug != "" and snapshot_project_slug != context_project_slug:
                continue

            snapshot_is_archived = (str(snapshot_entry.get("status", "")) == "archived")

            # 判定：語境唔夾（普通列面唔顯示歸檔對話；歸檔列面唔顯示普通對話）→ 跳過
            if snapshot_is_archived != context_is_archive:
                continue

        observed_entry = observed_map.get(snapshot_key)

        # 判定：UI 冇呢個 key
        if not isinstance(observed_entry, dict):
            # 判定：呢類事件容許缺（例如訊息窗只渲染一部分）→ 唔算唔一致
            if event_name in _MISMATCH_ALLOW_MISSING:
                continue

            mismatch_key_list.append(snapshot_key)
            continue

        for field_name in field_name_list:
            # 判定：文件冇呢個欄位 → 唔使比
            if field_name not in snapshot_entry:
                continue

            # 判定：值唔同 → 對唔上（逐 key 只記一次）
            if str(observed_entry.get(field_name, "")) != str(snapshot_entry[field_name]):
                mismatch_key_list.append(snapshot_key)
                break

    return mismatch_key_list

def _is_repush_needed(event_name: str, stream_id: str, pending_version: int, expected_snapshot: Mapping[str, object] | None, retry_count: int, context_project_slug: str = "", context_is_archive: bool = False) -> bool:
    """ACK 已到但 UI 現況未收斂 → True（要出新 doc）；已到重推上限 → False。"""
    # 判定：冇 pending 文件或已到上限 → 唔再推
    if expected_snapshot is None or retry_count >= SNAPSHOT_MAX_RETRY_COUNT:
        return False

    observed_items = _read_observed(stream_id, event_name, pending_version)

    # 判定：前端未回報 UI 現況 → 當未收斂（等下拍再驗）
    if observed_items is None:
        return False

    mismatch_key_list = _find_mismatch(event_name, expected_snapshot, observed_items, context_project_slug, context_is_archive)

    # 判定：對得上 → 清重複登記、唔使再推
    if len(mismatch_key_list) == 0:
        _ = _MISMATCH_REPEAT.pop(stream_id + "|" + event_name, None)

        return False

    # 同一個唔一致簽名連續出現 → 前端修唔到，停止重推
    repeat_key = stream_id + "|" + event_name
    mismatch_signature = ",".join(sorted(mismatch_key_list))
    last_signature, last_count = _MISMATCH_REPEAT.get(repeat_key, ("", 0))

    # 判定：簽名同上次一樣 → 次數 +1；否則重新計
    if last_signature == mismatch_signature:
        repeat_count = last_count + 1
    else:
        repeat_count = 1

    _MISMATCH_REPEAT[repeat_key] = (mismatch_signature, repeat_count)

    # 判定：連續到上限 → 唔再推（等下一次真變化）
    if repeat_count >= SNAPSHOT_MISMATCH_REPEAT_LIMIT:
        return False

    return True


def _build_project_snapshot() -> dict[str, dict[str, object]]:
    """由 sqlite 讀專案完整快照（{project_slug: {status, updated_at, chat_count}}）——欄位可加減。"""
    db_path = Path(LOCALDB_DIR) / "hkai_chat_data.sqlite"

    # 判定：DB 檔唔存在 → 空快照
    if db_path.is_file() is False:
        return {}

    project_snapshot: dict[str, dict[str, object]] = {}

    try:
        connection = sqlite3.connect("file:" + db_path.as_posix() + "?mode=ro", uri=True, timeout=2)

        project_sql = (
            "SELECT project_id, project_slug, project_name, project_status, project_sort_index,"
            " project_updated_at, chat_updated_at, provider_id, model_id"
            " FROM projects ORDER BY project_sort_index ASC"
        )

        project_rows = connection.execute(project_sql).fetchall()

        count_rows = connection.execute("SELECT project_id, COUNT(*) FROM chats GROUP BY project_id").fetchall()

        connection.close()
    except sqlite3.Error:
        return {}

    chat_count_map: dict[int, int] = {}

    for count_row in count_rows:
        chat_count_map[int(count_row[0])] = int(count_row[1])

    for project_row in project_rows:
        project_slug = str(project_row[1])

        project_provider_id = str(project_row[7] or "")
        project_model_id = str(project_row[8] or "")
        project_model_text = (project_provider_id + "/" + project_model_id).strip("/")

        # 卡面更新時間：有對話更新就用佢，否則用專案更新時間
        project_updated_at = str(project_row[6] or "")

        # 判定：冇對話更新時間 → 退回專案更新時間
        if project_updated_at == "":
            project_updated_at = str(project_row[5] or "")

        project_entry: dict[str, object] = {}
        project_entry["project_id"] = int(project_row[0])
        project_entry["name"] = str(project_row[2] or "")
        project_entry["status"] = str(project_row[3] or "")
        project_entry["sort_index"] = int(project_row[4] or 0)
        project_entry["updated_at"] = project_updated_at
        project_entry["chat_count"] = chat_count_map.get(int(project_row[0]), 0)
        project_entry["model"] = project_model_text

        project_snapshot[project_slug] = project_entry

    return project_snapshot


def _format_event(event_name: str, payload: Mapping[str, object]) -> str:
    """砌具名 SSE 幀（event＋data 兩行）。"""
    payload_json = json.dumps(payload, ensure_ascii=False)

    return "event: " + event_name + chr(10) + "data: " + payload_json + chr(10) + chr(10)


def _format_manifest(payload: Mapping[str, object]) -> str:
    """砌無名 SSE 幀（連線第一幀＝事件名單，前端照名單自行掛 listener）。"""
    payload_json = json.dumps(payload, ensure_ascii=False)

    return "data: " + payload_json + chr(10) + chr(10)


def _build_chat_snapshot() -> dict[str, dict[str, object]]:
    """由 sqlite 讀對話快照（{chat_sid: {project_slug, title, status, updated_at, msg_count}}）。"""
    db_path = Path(LOCALDB_DIR) / "hkai_chat_data.sqlite"

    # 判定：DB 檔唔存在 → 空快照
    if db_path.is_file() is False:
        return {}

    chat_snapshot: dict[str, dict[str, object]] = {}

    try:
        connection = sqlite3.connect("file:" + db_path.as_posix() + "?mode=ro", uri=True, timeout=2)

        chat_sql = (
            "SELECT c.chat_sid, c.chat_title, c.chat_status, c.chat_updated_at, c.chat_message_count,"
            " p.project_slug"
            " FROM chats c LEFT JOIN projects p ON p.project_id = c.project_id"
            " ORDER BY c.chat_updated_at DESC"
        )

        chat_rows = connection.execute(chat_sql).fetchall()

        connection.close()
    except sqlite3.Error:
        return {}

    for chat_row in chat_rows:
        chat_entry: dict[str, object] = {}
        chat_entry["project_slug"] = str(chat_row[5] or "")
        chat_entry["title"] = str(chat_row[1] or "")
        chat_entry["status"] = str(chat_row[2] or "")
        chat_entry["updated_at"] = str(chat_row[3] or "")
        chat_entry["msg_count"] = int(chat_row[4] or 0)

        chat_snapshot[str(chat_row[0])] = chat_entry

    return chat_snapshot


def _build_message_snapshot(chat_sid: str) -> dict[str, dict[str, object]]:
    """由 sqlite 讀當前對話訊息快照（{message_id: {status, updated_at}}——前端據此校正卡狀態）。"""
    # 判定：未選對話 → 空快照
    if chat_sid == "":
        return {}

    db_path = Path(LOCALDB_DIR) / "hkai_chat_data.sqlite"

    # 判定：DB 檔唔存在 → 空快照
    if db_path.is_file() is False:
        return {}

    message_snapshot: dict[str, dict[str, object]] = {}

    try:
        connection = sqlite3.connect("file:" + db_path.as_posix() + "?mode=ro", uri=True, timeout=2)

        chat_row = connection.execute("SELECT chat_id FROM chats WHERE chat_sid = ?", (chat_sid,)).fetchone()

        # 判定：查唔到對話 → 空快照
        if chat_row is None:
            connection.close()

            return {}

        chat_id = int(chat_row[0])

        message_sql = (
            "SELECT message_id, message_status, message_update_at FROM messages"
            " WHERE chat_id = ? ORDER BY message_id ASC"
        )

        message_rows = connection.execute(message_sql, (chat_id,)).fetchall()

        connection.close()
    except sqlite3.Error:
        return {}

    for message_row in message_rows:
        message_entry: dict[str, object] = {}
        message_entry["status"] = str(message_row[1] or "")
        message_entry["updated_at"] = str(message_row[2] or "")

        message_snapshot[str(message_row[0])] = message_entry

    return message_snapshot


async def _workspace_stream(project_slug: str, project_path: str, chat_sid: str):
    """SSE 串流：polling／git／project／chat／message 快照；四類快照用 ACK ＋有上限重推（at-least-once）。"""
    started_at = time.monotonic()
    last_git_json = ""
    tick_seconds = WORKSPACE_TICK_SECONDS

    # 本連線身份（ACK 分線用）
    stream_id = uuid.uuid4().hex

    # 清單語境（URL 對話段係 archive／archive@<sid> ＝歸檔列面）
    context_is_archive = (chat_sid == "archive") or (chat_sid.startswith("archive@") is True)

    # 判定：ACK 登記過多（大量重載）→ 清一次（未完成嘅會重推至上限後停）
    if len(_ACK_VERSION) > 500:
        _ACK_VERSION.clear()
        _ACK_OBSERVED.clear()
        _MISMATCH_REPEAT.clear()

    # 專案快照狀態（version 只會遞增；未 ACK 就重推同一份）
    project_version = 0
    project_snapshot_last: dict[str, dict[str, object]] = {}
    project_snapshot_pending: dict[str, dict[str, object]] | None = None
    project_pending_version = 0
    project_retry_count = 0
    project_sent_at = 0.0

    # 對話快照狀態（同 project 同一套 ACK／重推）
    chat_version = 0
    chat_snapshot_last: dict[str, dict[str, object]] = {}
    chat_snapshot_pending: dict[str, dict[str, object]] | None = None
    chat_pending_version = 0
    chat_retry_count = 0
    chat_sent_at = 0.0

    # 訊息快照狀態（同 project／chat 同一套）
    message_version = 0
    message_snapshot_last: dict[str, dict[str, object]] = {}
    message_snapshot_pending: dict[str, dict[str, object]] | None = None
    message_pending_version = 0
    message_retry_count = 0
    message_sent_at = 0.0

    # polling 快照狀態（同 project／chat／message 同一套 ACK／重推；無變唔推）
    polling_version = 0
    polling_snapshot_last: Mapping[str, object] = {}
    polling_snapshot_pending: Mapping[str, object] | None = None
    polling_pending_version = 0
    polling_retry_count = 0
    polling_sent_at = 0.0

    # 第一幀：事件名單（無名）——前端照名單自行掛 listener，前端唔寫死任何事件名
    yield _format_manifest({"events": ["git", "polling", "project", "chat", "message"]})

    while True:
        tick_started_at = time.monotonic()

        git_entry = await asyncio.to_thread(_build_git_entry, project_path)

        git_payload = {"project_slug": project_slug, "entry": git_entry}
        git_json = json.dumps(git_payload, ensure_ascii=False, sort_keys=True)

        # 判定：狀態有變（首 tick 一定推）→ 送 git 事件
        if git_json != last_git_json:
            last_git_json = git_json

            yield _format_event("git", git_payload)

        # polling 快照：有變 → 版本 +1 即刻推；未 ACK → 每 3 秒重推同一份（上限 20 次）
        live_polling_snapshot = _build_polling_payload()

        # 判定：polling 有變 → 起新版本、即刻推
        if live_polling_snapshot != polling_snapshot_last:
            polling_snapshot_last = live_polling_snapshot
            polling_version = polling_version + 1
            polling_snapshot_pending = live_polling_snapshot
            polling_pending_version = polling_version
            polling_retry_count = 0
            polling_sent_at = time.monotonic()

            yield _format_event("polling", {
                "stream_id": stream_id,
                "version": polling_pending_version,
                "snapshot": polling_snapshot_pending,
            })
        else:
            polling_acked_version = _read_acked_version(stream_id, "polling")

            # 判定：未 ACK 且夠鐘且未到上限 → 重推同一份（冪等：前端整份覆寫）
            if polling_snapshot_pending is not None and polling_acked_version < polling_pending_version:
                is_polling_retry_due = time.monotonic() - polling_sent_at >= SNAPSHOT_RETRY_INTERVAL_SECONDS
                is_polling_retry_left = polling_retry_count < SNAPSHOT_MAX_RETRY_COUNT

                if is_polling_retry_due and is_polling_retry_left:
                    polling_retry_count = polling_retry_count + 1
                    polling_sent_at = time.monotonic()

                    yield _format_event("polling", {
                        "stream_id": stream_id,
                        "version": polling_pending_version,
                        "snapshot": polling_snapshot_pending,
                    })

            # 判定：ACK 已到但 UI 現況仍未收斂 → 起新版本再推（收斂迴圈；上限 20 次）
            is_polling_converged = polling_acked_version >= polling_pending_version
            is_polling_repush = _is_repush_needed("polling", stream_id, polling_pending_version, polling_snapshot_pending, polling_retry_count, project_slug)

            if is_polling_converged and is_polling_repush:
                polling_retry_count = polling_retry_count + 1
                polling_version = polling_version + 1
                polling_pending_version = polling_version
                polling_sent_at = time.monotonic()

                yield _format_event("polling", {
                    "stream_id": stream_id,
                    "version": polling_pending_version,
                    "snapshot": polling_snapshot_pending,
                })

        # 專案快照：有變 → 版本 +1 即刻推；未 ACK → 每 3 秒重推同一份（上限 20 次）
        live_project_snapshot = _build_project_snapshot()

        # 判定：專案資料有變 → 起新版本、即刻推
        if live_project_snapshot != project_snapshot_last:
            project_snapshot_last = live_project_snapshot
            project_version = project_version + 1
            project_snapshot_pending = live_project_snapshot
            project_pending_version = project_version
            project_retry_count = 0
            project_sent_at = time.monotonic()

            yield _format_event("project", {
                "stream_id": stream_id,
                "version": project_pending_version,
                "snapshot": project_snapshot_pending,
            })
        else:
            acked_version = _read_acked_version(stream_id, "project")

            # 判定：未 ACK 且夠鐘且未到上限 → 重推同一份（冪等：前端整份覆寫）
            if project_snapshot_pending is not None and acked_version < project_pending_version:
                is_retry_due = time.monotonic() - project_sent_at >= SNAPSHOT_RETRY_INTERVAL_SECONDS
                is_retry_left = project_retry_count < SNAPSHOT_MAX_RETRY_COUNT

                if is_retry_due and is_retry_left:
                    project_retry_count = project_retry_count + 1
                    project_sent_at = time.monotonic()

                    yield _format_event("project", {
                        "stream_id": stream_id,
                        "version": project_pending_version,
                        "snapshot": project_snapshot_pending,
                    })

            # 判定：ACK 已到但 UI 現況仍未收斂 → 起新版本再推（收斂迴圈；上限 20 次）
            is_project_converged = acked_version >= project_pending_version
            is_project_repush = _is_repush_needed("project", stream_id, project_pending_version, project_snapshot_pending, project_retry_count, project_slug)

            if is_project_converged and is_project_repush:
                project_retry_count = project_retry_count + 1
                project_version = project_version + 1
                project_pending_version = project_version
                project_sent_at = time.monotonic()

                yield _format_event("project", {
                    "stream_id": stream_id,
                    "version": project_pending_version,
                    "snapshot": project_snapshot_pending,
                })

        # 對話快照：同 project 同一套（有變即推；未 ACK 每 3 秒重推、上限 20 次）
        live_chat_snapshot = _build_chat_snapshot()

        # 判定：對話資料有變 → 起新版本、即刻推
        if live_chat_snapshot != chat_snapshot_last:
            chat_snapshot_last = live_chat_snapshot
            chat_version = chat_version + 1
            chat_snapshot_pending = live_chat_snapshot
            chat_pending_version = chat_version
            chat_retry_count = 0
            chat_sent_at = time.monotonic()

            yield _format_event("chat", {
                "stream_id": stream_id,
                "version": chat_pending_version,
                "snapshot": chat_snapshot_pending,
            })
        else:
            chat_acked_version = _read_acked_version(stream_id, "chat")

            # 判定：未 ACK 且夠鐘且未到上限 → 重推同一份
            if chat_snapshot_pending is not None and chat_acked_version < chat_pending_version:
                is_chat_retry_due = time.monotonic() - chat_sent_at >= SNAPSHOT_RETRY_INTERVAL_SECONDS
                is_chat_retry_left = chat_retry_count < SNAPSHOT_MAX_RETRY_COUNT

                if is_chat_retry_due and is_chat_retry_left:
                    chat_retry_count = chat_retry_count + 1
                    chat_sent_at = time.monotonic()

                    yield _format_event("chat", {
                        "stream_id": stream_id,
                        "version": chat_pending_version,
                        "snapshot": chat_snapshot_pending,
                    })

            # 判定：ACK 已到但 UI 現況仍未收斂 → 起新版本再推（收斂迴圈；上限 20 次）
            is_chat_converged = chat_acked_version >= chat_pending_version
            is_chat_repush = _is_repush_needed("chat", stream_id, chat_pending_version, chat_snapshot_pending, chat_retry_count, project_slug, context_is_archive)

            if is_chat_converged and is_chat_repush:
                chat_retry_count = chat_retry_count + 1
                chat_version = chat_version + 1
                chat_pending_version = chat_version
                chat_sent_at = time.monotonic()

                yield _format_event("chat", {
                    "stream_id": stream_id,
                    "version": chat_pending_version,
                    "snapshot": chat_snapshot_pending,
                })

        # 訊息快照：同 project／chat 同一套（有變即推；未 ACK 重推上限 20 次）
        live_message_snapshot = _build_message_snapshot(chat_sid)

        # 判定：訊息資料有變 → 起新版本、即刻推
        if live_message_snapshot != message_snapshot_last:
            message_snapshot_last = live_message_snapshot
            message_version = message_version + 1
            message_snapshot_pending = live_message_snapshot
            message_pending_version = message_version
            message_retry_count = 0
            message_sent_at = time.monotonic()

            yield _format_event("message", {
                "stream_id": stream_id,
                "version": message_pending_version,
                "snapshot": message_snapshot_pending,
            })
        else:
            message_acked_version = _read_acked_version(stream_id, "message")

            # 判定：未 ACK 且夠鐘且未到上限 → 重推同一份
            if message_snapshot_pending is not None and message_acked_version < message_pending_version:
                is_message_retry_due = time.monotonic() - message_sent_at >= SNAPSHOT_RETRY_INTERVAL_SECONDS
                is_message_retry_left = message_retry_count < SNAPSHOT_MAX_RETRY_COUNT

                if is_message_retry_due and is_message_retry_left:
                    message_retry_count = message_retry_count + 1
                    message_sent_at = time.monotonic()

                    yield _format_event("message", {
                        "stream_id": stream_id,
                        "version": message_pending_version,
                        "snapshot": message_snapshot_pending,
                    })

            # 判定：ACK 已到但 UI 現況仍未收斂 → 起新版本再推（收斂迴圈；上限 20 次）
            is_message_converged = message_acked_version >= message_pending_version
            is_message_repush = _is_repush_needed("message", stream_id, message_pending_version, message_snapshot_pending, message_retry_count, project_slug)

            if is_message_converged and is_message_repush:
                message_retry_count = message_retry_count + 1
                message_version = message_version + 1
                message_pending_version = message_version
                message_sent_at = time.monotonic()

                yield _format_event("message", {
                    "stream_id": stream_id,
                    "version": message_pending_version,
                    "snapshot": message_snapshot_pending,
                })

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
    chat_sid = request.query_params.get("chat_sid", "")

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
        _workspace_stream(project_slug, project_path, chat_sid),
        media_type="text/event-stream",
        headers=response_headers,
    )


@router.post("/workspace_ack")
async def workspace_ack(request: Request):
    """前端確認「已套用某版本快照」（ACK）——後台據此停止重推。"""
    origin = request.headers.get("origin", "")
    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return PlainTextResponse("origin not allowed", status_code=403)

    try:
        ack_payload = await request.json()
    except ValueError:
        return PlainTextResponse("invalid json", status_code=400)

    # 判定：payload 唔係 dict → 400
    if isinstance(ack_payload, dict) is False:
        return PlainTextResponse("invalid payload", status_code=400)

    stream_id = str(ack_payload.get("stream_id", ""))
    event_name = str(ack_payload.get("event", ""))
    version_text = str(ack_payload.get("version", "0"))

    # 判定：identity 不齊 → 400
    if stream_id == "" or event_name == "":
        return PlainTextResponse("stream_id or event missing", status_code=400)

    try:
        acked_version = int(version_text)
    except ValueError:
        return PlainTextResponse("invalid version", status_code=400)

    ack_key = _ack_key(stream_id, event_name)
    current_version = int(_ACK_VERSION.get(ack_key, 0))

    # 判定：新版本更高 → 記低（只升不降）
    if acked_version > current_version:
        _ACK_VERSION[ack_key] = acked_version

    # UI 現況回報（前端隨 ACK 帶上；收斂比對用——冇帶＝只當已收到）
    observed_payload = ack_payload.get("observed", None)

    # 判定：有帶且係 dict → 記低（前端包成 {items: {...}}，此處拆出 items）
    if isinstance(observed_payload, dict):
        observed_items = observed_payload.get("items", observed_payload)

        # 判定：拆出嚟仍然係 dict → 記低
        if isinstance(observed_items, dict):
            _write_observed(stream_id, event_name, acked_version, observed_items)

    response_headers: dict[str, str] = {}

    response_headers["Access-Control-Allow-Origin"] = origin if origin != "" else "*"

    return PlainTextResponse("ok", status_code=200, headers=response_headers)


@router.options("/workspace_ack")
def workspace_ack_options(request: Request):
    """ACK 端點嘅 CORS 預檢。"""
    origin = request.headers.get("origin", "")
    allowed_origins = read_origin_allowlist()

    # 判定：origin 唔喺允許清單 → 403
    if origin != "" and origin_is_allowed(origin, allowed_origins) is False:
        return PlainTextResponse("origin not allowed", status_code=403)

    response_headers = {
        "Access-Control-Allow-Methods": "POST, OPTIONS",
        "Access-Control-Allow-Headers": request.headers.get("access-control-request-headers", ""),
        "Access-Control-Max-Age": "600",
    }

    # 判定：有 origin → 回 CORS 標頭
    if origin != "":
        response_headers["Access-Control-Allow-Origin"] = origin
        response_headers["Vary"] = "Origin"

    return PlainTextResponse("", status_code=204, headers=response_headers)


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

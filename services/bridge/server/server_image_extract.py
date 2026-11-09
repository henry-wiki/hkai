#!/usr/bin/env python3
"""
server_image_extract.py — 圖片抽取端點（PHP 只 POST，唔等結果）。

職責：
- POST /image_extract/start/  ：驗 payload → 寫 job.json → launcher --bg 拉起 image_extractor → 即回
- POST /image_extract/stop/   ：跑 image_extractor stop（殺進程樹＋標記 cancelled）→ 回結果

設計：
- 抽取工作一律喺 image_extractor 子進程（唔喺 bridge 內做——OCR 會阻塞 event loop）
- 子進程經 launcher 拉起（launcher 即刻退場 → 子進程脫離 bridge 生命週期）
- 狀態真相＝data/_tmp/image_extract/<message_id>/<idx>.status.json（一個檔案一個真相）

上游：PHP（bridge_Class::request）
下游：launcher（spawn）、image_extractor（run／stop）、sc_logger
"""

# pyright: reportExplicitAny=false, reportAny=false
# （payload／job.json／status.json 動態——Any 警告一律關，其餘照檢）

import json
import re
import subprocess
import sys
import time
from pathlib import Path
from typing import Any, cast

from fastapi import APIRouter

from ..context import DATA_DIR, SERVICES_DIR
from bootstrap import sc_logger
from image_extractor import box_store
from image_extractor.runtime import pidfile

router = APIRouter()

# 附件／紙箱根目錄（job 內檔案只准喺 data/messages 之下——紙箱附件住 <box>/attachments/）
MESSAGES_DIR = DATA_DIR / "messages"

# 每條訊息最多圖片數（同前端／PHP 契約一致）
MAX_IMAGES_PER_MESSAGE = 13

# launcher 拉起寬限（秒——launcher 只負責 spawn，正常 <1 秒）
LAUNCH_TIMEOUT_SEC = 20

# stop 寬限（秒——內部 terminate 等 3 秒＋進程啟動）
STOP_TIMEOUT_SEC = 20


def _error(message: str) -> dict[str, Any]:
    """統一錯誤回應（HTTP 200＋ok:false——同其他 bridge 端點一致）。"""
    sc_logger.log("image_extract error: " + message)

    return {"ok": False, "error": message}


def _validate_identity(payload: dict[str, Any]) -> tuple[dict[str, Any], str]:
    """驗身份四欄（project_slug／chat_sid／message_id／chat_id）；回 (identity, error)。"""
    message_id = int(cast(int, payload.get("message_id", 0)))
    chat_id = int(cast(int, payload.get("chat_id", 0)))
    chat_sid = str(payload.get("chat_sid", ""))
    project_slug = str(payload.get("project_slug", ""))

    # 判定：message_id／chat_id 無效 → 錯
    if message_id < 1 or chat_id < 1:
        return {}, "message_id and chat_id are required"

    # 判定：chat_sid 格式唔啱 → 錯
    if re.fullmatch(r"[A-Za-z0-9_-]{8,64}", chat_sid) is None:
        return {}, "invalid chat_sid"

    # 判定：project_slug 格式唔啱 → 錯
    if re.fullmatch(r"[a-z0-9_-]{1,64}", project_slug) is None:
        return {}, "invalid project_slug"

    identity: dict[str, Any] = {}
    identity["message_id"] = message_id
    identity["chat_id"] = chat_id
    identity["chat_sid"] = chat_sid
    identity["project_slug"] = project_slug

    return identity, ""


@router.post("/image_extract/start/")
def start(payload: dict[str, Any]) -> dict[str, Any]:
    """接收抽取工作：寫 job.json → 拉起 extractor → 即回（唔等結果）。"""
    started_time = time.time()

    identity, validate_error = _validate_identity(payload)

    # 判定：身份驗證失敗 → 回錯
    if validate_error != "":
        return _error(validate_error)

    message_id = int(identity["message_id"])
    project_slug = str(identity["project_slug"])
    chat_sid = str(identity["chat_sid"])

    box_dir = box_store.resolve_box_dir(project_slug, chat_sid, message_id)

    # 判定：搵唔到箱 → 回錯（PHP 只會對 polling 箱派工）
    if box_dir is None:
        return _error("box not found: message_id=" + str(message_id))

    meta_data = box_store.read_meta(box_dir)

    # 判定：meta 讀唔到 → 回錯
    if meta_data is None:
        return _error("meta.json invalid: " + str(box_dir))

    clean_files = box_store.list_attachment_files(box_dir, meta_data)

    # 判定：圖片超限 → 回錯（其餘工作留返 PHP 擋）
    if len(clean_files) > MAX_IMAGES_PER_MESSAGE:
        return _error("files count over limit: " + str(len(clean_files)))

    # 判定：已有活主人（箱內 extract.pid）→ 唔重複開
    running_pid = pidfile.read_pid(box_dir)

    if pidfile.is_pid_running(running_pid) is True:
        return {"ok": True, "already_running": True, "files": len(clean_files), "elapsed_ms": 0}

    command = [
        sys.executable,
        "-u",
        "-m",
        "launcher.main",
        "image_extractor",
        "--bg",
        "run",
        str(box_dir),
    ]

    try:
        completed = subprocess.run(
            command,
            cwd=str(SERVICES_DIR),
            capture_output=True,
            text=True,
            timeout=LAUNCH_TIMEOUT_SEC,
        )
    except subprocess.TimeoutExpired:
        return _error("launcher timeout")

    # 判定：launcher 非 0 退出 → 回錯（帶 stderr）
    if completed.returncode != 0:
        error_text = str(completed.stderr).strip()
        return _error("launcher failed: " + error_text)

    elapsed_ms = int((time.time() - started_time) * 1000)
    sc_logger.log(
        "image_extract start: message_id=" + str(message_id) + " files=" + str(len(clean_files)) + " (" + str(elapsed_ms) + "ms)"
    )

    return {"ok": True, "job_id": str(message_id), "files": len(clean_files), "elapsed_ms": elapsed_ms}


@router.post("/image_extract/stop/")
def stop(payload: dict[str, Any]) -> dict[str, Any]:
    """停止抽取：殺進程樹 → 標記 cancelled（已完成者保留）。"""
    box_dir = Path(str(payload.get("box_dir", ""))).resolve()

    # 判定：路徑唔喺 data/messages 之下 → 拒絕
    if MESSAGES_DIR.resolve() not in box_dir.parents:
        return _error("box_dir outside messages dir")

    # 判定：箱唔存在 → 冇嘢可停（ok:true——idempotent）
    if box_dir.is_dir() is False:
        return {"ok": True, "killed": False, "pid": 0}

    command = [
        sys.executable,
        "-u",
        "-m",
        "image_extractor.main",
        "stop",
        str(box_dir),
    ]

    try:
        completed = subprocess.run(
            command,
            cwd=str(SERVICES_DIR),
            capture_output=True,
            text=True,
            timeout=STOP_TIMEOUT_SEC,
        )
    except subprocess.TimeoutExpired:
        return _error("stop timeout")

    # 判定：stop CLI 失敗 → 回錯（帶 stderr）
    if completed.returncode != 0:
        return _error("stop failed: " + str(completed.stderr).strip())

    result_text = str(completed.stdout).strip()
    sc_logger.log("image_extract stop: box=" + str(box_dir) + " result=" + result_text)

    try:
        stop_result = json.loads(result_text)
    except ValueError:
        return _error("stop returned invalid json")

    return cast(dict[str, Any], stop_result)

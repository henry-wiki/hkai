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
import subprocess
import sys
import time
from pathlib import Path
from typing import Any, cast

from fastapi import APIRouter

from ..context import DATA_DIR, SERVICES_DIR
from ..runtime import sc_logger

router = APIRouter()

# 抽取工作根目錄（每個 message 一個子目錄）
JOBS_DIR = DATA_DIR / "_tmp" / "image_extract"

# 附件根目錄（job 內檔案只准喺呢度之下）
ATTACHMENTS_DIR = DATA_DIR / "messages" / "attachments"

# 每條訊息最多圖片數（同前端／PHP 契約一致）
MAX_IMAGES_PER_MESSAGE = 3

# launcher 拉起寬限（秒——launcher 只負責 spawn，正常 <1 秒）
LAUNCH_TIMEOUT_SEC = 20

# stop 寬限（秒——內部 terminate 等 3 秒＋進程啟動）
STOP_TIMEOUT_SEC = 20


def _error(message: str) -> dict[str, Any]:
    """統一錯誤回應（HTTP 200＋ok:false——同其他 bridge 端點一致）。"""
    sc_logger.log("image_extract error: " + message)

    return {"ok": False, "error": message}


def _validate_files(payload: dict[str, Any]) -> tuple[list[dict[str, Any]], str]:
    """驗 images 清單；回 (files, error)。"""
    raw_files = payload.get("files")

    # 判定：files 唔係陣列 → 錯
    if isinstance(raw_files, list) is False:
        return [], "files must be a list"

    file_list = cast(list[Any], raw_files)

    # 判定：張數超限或為空 → 錯
    if len(file_list) < 1 or len(file_list) > MAX_IMAGES_PER_MESSAGE:
        return [], "files count must be 1-" + str(MAX_IMAGES_PER_MESSAGE)

    attachments_root = ATTACHMENTS_DIR.resolve()
    clean_files: list[dict[str, Any]] = []
    seen_indexes: set[int] = set()

    for raw_item in file_list:
        # 判定：單項唔係物件 → 錯
        if isinstance(raw_item, dict) is False:
            return [], "file item must be an object"

        file_index = int(cast(int, raw_item.get("idx", 0)))
        file_path_text = str(raw_item.get("path", ""))
        file_mime = str(raw_item.get("mime", ""))

        # 判定：idx 範圍錯 → 錯
        if file_index < 1 or file_index > MAX_IMAGES_PER_MESSAGE:
            return [], "idx out of range: " + str(file_index)

        # 判定：idx 重複 → 錯
        if file_index in seen_indexes:
            return [], "duplicate idx: " + str(file_index)

        # 判定：路徑空白 → 錯
        if file_path_text == "":
            return [], "path is required"

        file_path = Path(file_path_text).resolve()

        # 判定：唔喺附件目錄之下 → 拒絕
        if attachments_root not in file_path.parents:
            return [], "path outside attachments dir: " + file_path_text

        # 判定：檔案唔存在 → 錯
        if file_path.is_file() is False:
            return [], "file not found: " + file_path_text

        seen_indexes.add(file_index)
        clean_files.append({"idx": file_index, "path": str(file_path), "mime": file_mime})

    clean_files.sort(key=lambda file_item: int(file_item["idx"]))

    return clean_files, ""


@router.post("/image_extract/start/")
def start(payload: dict[str, Any]) -> dict[str, Any]:
    """接收抽取工作：寫 job.json → 拉起 extractor → 即回（唔等結果）。"""
    started_time = time.time()

    message_id = int(cast(int, payload.get("message_id", 0)))

    # 判定：message_id 無效 → 錯
    if message_id < 1:
        return _error("message_id is required")

    clean_files, validate_error = _validate_files(payload)

    # 判定：驗證失敗 → 回錯
    if validate_error != "":
        return _error(validate_error)

    job_dir = JOBS_DIR / str(message_id)
    job_dir.mkdir(parents=True, exist_ok=True)

    job_data: dict[str, Any] = {
        "message_id": message_id,
        "chat_id": int(cast(int, payload.get("chat_id", 0))),
        "chat_sid": str(payload.get("chat_sid", "")),
        "project_slug": str(payload.get("project_slug", "")),
        "files": clean_files,
        "created_at": time.strftime("%Y-%m-%d %H:%M:%S"),
    }

    job_file = job_dir / "job.json"
    temp_file = job_dir / "job.json.tmp"
    _ = temp_file.write_text(json.dumps(job_data, ensure_ascii=False, indent=2), encoding="utf-8")
    _ = temp_file.replace(job_file)

    command = [
        sys.executable,
        "-u",
        "-m",
        "launcher.main",
        "image_extractor",
        "--bg",
        "run",
        str(job_dir),
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
    message_id = int(cast(int, payload.get("message_id", 0)))

    # 判定：message_id 無效 → 錯
    if message_id < 1:
        return _error("message_id is required")

    job_dir = JOBS_DIR / str(message_id)

    # 判定：job 目錄唔存在 → 冇嘢可停（ok:true——idempotent）
    if job_dir.is_dir() is False:
        return {"ok": True, "killed": False, "pid": 0}

    command = [
        sys.executable,
        "-u",
        "-m",
        "image_extractor.main",
        "stop",
        str(job_dir),
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
    sc_logger.log("image_extract stop: message_id=" + str(message_id) + " result=" + result_text)

    try:
        stop_result = json.loads(result_text)
    except ValueError:
        return _error("stop returned invalid json")

    return cast(dict[str, Any], stop_result)

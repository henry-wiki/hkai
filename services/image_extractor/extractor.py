# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false, reportUnusedCallResult=false
#!/usr/bin/env python3
"""
extractor.py — 逐張圖片抽取編排（一次一張；完成才下一張）。

職責：
- 讀 job.json → 逐張：狀態 running → basic／visual／qr／ocr → 寫 meta json → 狀態 done
- 任何一張失敗：記 failed（唔阻其餘）
- cancel()：由 main 嘅訊號處理呼叫——running／pending → cancelled

上游：main（run）
下游：job_store、pipeline（basic／visual／ocr／qr）、runtime.sc_logger
"""

import json
import time
from pathlib import Path
from typing import Any

import cv2

from . import context, job_store
from .pipeline import basic, ocr, qr, visual
from .runtime import sc_logger

# meta 格式版本（向後兼容用）
META_VERSION = 1


class extractor_class:
    """一次 job 的抽取器（一個 message 一個 job 目錄）。"""

    def __init__(self, job_dir: Path) -> None:
        self.job_dir: Path = job_dir
        self.job_data: dict[str, Any] = {}

    def run(self) -> int:
        """執行整份 job；回成功張數（失敗照繼續）。"""
        self.job_data = job_store.read_job(self.job_dir)

        # 判定：job.json 缺失／壞 → 唔做
        if self.job_data == {}:
            sc_logger.log("JOB_INVALID", "job.json missing or invalid: " + str(self.job_dir))
            return 0

        job_files = self.job_data.get("files", [])

        # 判定：冇檔案 → 唔做
        if isinstance(job_files, list) is False or len(job_files) < 1:
            sc_logger.log("JOB_EMPTY", "no files in job: " + str(self.job_dir))
            return 0

        success_count = 0

        for job_file_item in job_files:
            file_index = int(job_file_item.get("idx", 0))
            file_path_text = str(job_file_item.get("path", ""))

            current_status = job_store.read_file_status(self.job_dir, file_index)
            current_state = str(current_status.get("state", "pending"))

            # 判定：已完成／已取消 → 跳過（支援斷點續跑）
            if current_state in ("done", "cancelled"):
                continue

            is_success = self._process_one(file_index, file_path_text)

            # 判定：成功 → 計數
            if is_success is True:
                success_count += 1

        self.job_data["finished_at"] = time.strftime("%Y-%m-%d %H:%M:%S")
        job_store.write_job(self.job_dir, self.job_data)

        return success_count

    def cancel(self) -> None:
        """取消：running／pending → cancelled（已完成者不動）。"""
        job_files = self.job_data.get("files", [])

        # 判定：未載入 job → 讀一次
        if self.job_data == {}:
            self.job_data = job_store.read_job(self.job_dir)
            job_files = self.job_data.get("files", [])

        # 判定：files 格式唔啱 → 唔做
        if isinstance(job_files, list) is False:
            return

        for job_file_item in job_files:
            file_index = int(job_file_item.get("idx", 0))
            current_status = job_store.read_file_status(self.job_dir, file_index)
            current_state = str(current_status.get("state", "pending"))

            # 判定：已完成／已失敗 → 保留原狀
            if current_state in ("done", "failed"):
                continue

            current_status["state"] = "cancelled"
            job_store.write_file_status(self.job_dir, file_index, current_status)

        self.job_data["cancelled_at"] = time.strftime("%Y-%m-%d %H:%M:%S")
        job_store.write_job(self.job_dir, self.job_data)

    def _process_one(self, file_index: int, file_path_text: str) -> bool:
        """處理一張圖；成功回 True。"""
        file_path = Path(file_path_text).resolve()

        status_data: dict[str, Any] = {
            "idx": file_index,
            "state": "running",
            "path": str(file_path),
            "started_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        }
        job_store.write_file_status(self.job_dir, file_index, status_data)

        sc_logger.log("IMAGE_STARTED", "idx=" + str(file_index), {"path": str(file_path)})

        start_time = time.perf_counter()

        try:
            meta_data = self._analyze_image(file_index, file_path)
            meta_path = self._write_meta(file_path, meta_data)
            elapsed_ms = int((time.perf_counter() - start_time) * 1000)

            status_data["state"] = "done"
            status_data["finished_at"] = time.strftime("%Y-%m-%d %H:%M:%S")
            status_data["elapsed_ms"] = elapsed_ms
            status_data["meta_path"] = str(meta_path)
            status_data["ocr_chars"] = len(str(meta_data.get("summary_text", "")))
            job_store.write_file_status(self.job_dir, file_index, status_data)

            sc_logger.log(
                "IMAGE_DONE",
                "idx=" + str(file_index),
                {"elapsed_ms": elapsed_ms, "ocr_chars": status_data["ocr_chars"]},
            )

            return True
        except Exception as error:  # noqa: BLE001 —— 單張失敗唔可以拖死整份 job
            elapsed_ms = int((time.perf_counter() - start_time) * 1000)

            status_data["state"] = "failed"
            status_data["finished_at"] = time.strftime("%Y-%m-%d %H:%M:%S")
            status_data["elapsed_ms"] = elapsed_ms
            status_data["error"] = type(error).__name__ + ": " + str(error)
            job_store.write_file_status(self.job_dir, file_index, status_data)

            sc_logger.log("IMAGE_FAILED", status_data["error"], {"idx": file_index})

            return False

    def _analyze_image(self, file_index: int, file_path: Path) -> dict[str, Any]:
        """抽取一張圖的全部資料 → meta dict。"""
        self._assert_allowed_path(file_path)

        basic_info = basic.read_basic_info(file_path)
        image_array = cv2.imread(str(file_path), cv2.IMREAD_COLOR)

        visual_info: dict[str, Any] = {}
        ocr_info: dict[str, Any] = {"engine": "", "text": "", "lines": [], "elapsed_ms": 0}
        qr_list: list[dict[str, Any]] = []

        # 判定：解碼成功 → 跑視覺／QR／OCR
        if image_array is not None:
            visual_info = visual.read_visual_info(image_array)
            qr_list = qr.read_qr(image_array)
            ocr_info = ocr.read_ocr(image_array)

        summary_text = str(ocr_info.get("text", "")).strip()

        meta_data: dict[str, Any] = {
            "version": META_VERSION,
            "message_id": int(self.job_data.get("message_id", 0)),
            "chat_id": int(self.job_data.get("chat_id", 0)),
            "chat_sid": str(self.job_data.get("chat_sid", "")),
            "project_slug": str(self.job_data.get("project_slug", "")),
            "idx": int(file_index),
            "file": basic_info,
            "visual": visual_info,
            "ocr": ocr_info,
            "qr": qr_list,
            "summary_text": summary_text,
            "extracted_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        }

        return meta_data

    def _assert_allowed_path(self, file_path: Path) -> None:
        """只准讀附件目錄之下嘅檔案（防任意路徑）。"""
        allowed_root = context.ATTACHMENTS_DIR.resolve()

        # 判定：唔喺附件目錄之下 → 拒絕
        if allowed_root not in file_path.parents:
            raise PermissionError("path outside attachments dir: " + str(file_path))

    def _write_meta(self, image_path: Path, meta_data: dict[str, Any]) -> Path:
        """寫 meta json（同圖同目錄：<stem>.meta.json，atomic）。"""
        meta_path = image_path.with_suffix(".meta.json")
        temp_path = image_path.with_suffix(".meta.json.tmp")

        temp_path.write_text(json.dumps(meta_data, ensure_ascii=False, indent=2), encoding="utf-8")
        temp_path.replace(meta_path)

        return meta_path

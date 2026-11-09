# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false, reportUnusedCallResult=false
#!/usr/bin/env python3
"""
extractor.py — 逐張圖片抽取編排（一次一張；完成才下一張）。

職責：
- 讀箱內 meta.json → 逐張：basic／visual／qr／ocr → 寫 <圖名>.meta.json 喺圖旁邊
- 已有 <圖名>.meta.json ＝ 已完成（斷點續跑跳過）
- 全部處理完成（含個別失敗）→ 箱內 meta.json status：pending → polling

上游：main（run，<box_dir> 由 bridge 傳入）
下游：box_store（箱／meta／附件清單）、pipeline（basic／visual／ocr／qr）、runtime.sc_logger
"""

import json
import time
from pathlib import Path
from typing import Any

import cv2

from . import box_store, context
from .pipeline import basic, ocr, qr, visual
from .runtime import event_log

# meta 格式版本（向後兼容用）
META_VERSION = 1


class extractor_class:
    """一次抽取（box_dir＝紙箱：polling|archive/<slug>/<sid>/<prompt_id>）。"""

    def __init__(self, box_dir: Path) -> None:
        self.box_dir: Path = box_dir
        self.job_files: list[dict[str, Any]] = []

    def run(self) -> int:
        """執行整箱圖片抽取；回成功張數（失敗照繼續）。"""
        meta_data = box_store.read_meta(self.box_dir)

        # 判定：meta 讀唔到 → 唔做
        if meta_data is None:
            event_log.log_event("BOX_INVALID", "meta.json invalid: " + str(self.box_dir))
            return 0

        self.job_files = box_store.list_attachment_files(self.box_dir, meta_data)
        message_id = int(meta_data.get("message_id", 0))

        # 判定：冇圖片 → 直接跳過（正常路徑）＋放行
        if len(self.job_files) < 1:
            event_log.log_event("JOB_EMPTY", "no image attachment: message_id=" + str(message_id))
            _ = box_store.write_status(self.box_dir, "polling")
            return 0

        success_count = 0

        for job_file_item in self.job_files:
            file_index = int(job_file_item.get("idx", 0))
            file_path_text = str(job_file_item.get("path", ""))
            file_path = Path(file_path_text)
            result_path = file_path.with_suffix(".meta.json")

            # 判定：已有抽取結果 → 跳過（斷點續跑）
            if result_path.is_file() is True:
                continue

            is_success = self._process_one(file_index, file_path_text)

            # 判定：成功 → 計數
            if is_success is True:
                success_count += 1

        # 全部圖片處理完成（含個別失敗）→ 箱狀態 pending → polling（通知交 PHP）
        _ = box_store.write_status(self.box_dir, "polling")

        return success_count

    def _process_one(self, file_index: int, file_path_text: str) -> bool:
        """處理一張圖；成功回 True。"""
        file_path = Path(file_path_text).resolve()

        event_log.log_event("IMAGE_STARTED", "idx=" + str(file_index), {"path": str(file_path)})

        start_time = time.perf_counter()

        try:
            meta_data = self._analyze_image(file_index, file_path)
            elapsed_ms = int((time.perf_counter() - start_time) * 1000)
            meta_data["elapsed_ms"] = elapsed_ms
            meta_path = self._write_meta(file_path, meta_data)

            event_log.log_event(
                "IMAGE_DONE",
                "idx=" + str(file_index),
                {"elapsed_ms": elapsed_ms, "meta_path": str(meta_path)},
            )

            return True
        except Exception as error:  # noqa: BLE001 —— 單張失敗唔可以拖死整份 job
            elapsed_ms = int((time.perf_counter() - start_time) * 1000)

            failure_data: dict[str, Any] = {
                "version": META_VERSION,
                "state": "failed",
                "idx": int(file_index),
                "error": type(error).__name__ + ": " + str(error),
                "elapsed_ms": elapsed_ms,
                "extracted_at": time.strftime("%Y-%m-%d %H:%M:%S"),
            }

            # 失敗都要落檔（狀態睇得到；亦避免無限重試同一張）
            _ = self._write_meta(file_path, failure_data)

            event_log.log_event(
                "IMAGE_FAILED",
                type(error).__name__ + ": " + str(error),
                {"idx": file_index, "elapsed_ms": elapsed_ms},
            )

            return False

    def _analyze_image(self, file_index: int, file_path: Path) -> dict[str, Any]:
        """抽取一張圖的全部資料 → meta dict。"""
        self._assert_allowed_path(file_path)

        basic_info = basic.read_basic_info(file_path)
        image_array = cv2.imread(str(file_path), cv2.IMREAD_COLOR)

        # 判定：解碼失敗（壞檔／非圖片）→ 記 failed（唔可以靜靜當 done）
        if image_array is None:
            raise ValueError("image decode failed: " + file_path.name)

        visual_info: dict[str, Any] = {}
        ocr_info: dict[str, Any] = {"engine": "", "text": "", "lines": [], "elapsed_ms": 0}
        qr_list: list[dict[str, Any]] = []

        # 逐項防護——單項失敗唔拖死整張圖
        visual_info = self._run_visual_safe(image_array)
        qr_list = self._run_qr_safe(image_array)
        ocr_info = self._run_ocr_safe(image_array)

        summary_text = str(ocr_info.get("text", "")).strip()

        meta_data: dict[str, Any] = {
            "version": META_VERSION,
            "state": "done",
            "idx": int(file_index),
            "file": basic_info,
            "visual": visual_info,
            "ocr": ocr_info,
            "qr": qr_list,
            "summary_text": summary_text,
            "extracted_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        }

        return meta_data

    def _run_visual_safe(self, image_array: Any) -> dict[str, Any]:
        """視覺分析（失敗＝空 dict＋log；唔拖死整張圖）。"""
        try:
            return visual.read_visual_info(image_array)
        except Exception as error:  # noqa: BLE001 —— 單項防護
            event_log.log_event("VISUAL_FAILED", type(error).__name__ + ": " + str(error))
            return {}

    def _run_qr_safe(self, image_array: Any) -> list[dict[str, Any]]:
        """QR 解碼（失敗＝空清單＋log）。"""
        try:
            return qr.read_qr(image_array)
        except Exception as error:  # noqa: BLE001 —— 單項防護
            event_log.log_event("QR_FAILED", type(error).__name__ + ": " + str(error))
            return []

    def _run_ocr_safe(self, image_array: Any) -> dict[str, Any]:
        """OCR（失敗＝空結果＋log；summary_text 留空）。"""
        empty_ocr: dict[str, Any] = {"engine": "", "text": "", "lines": [], "elapsed_ms": 0}

        try:
            return ocr.read_ocr(image_array)
        except Exception as error:  # noqa: BLE001 —— 單項防護
            event_log.log_event("OCR_FAILED", type(error).__name__ + ": " + str(error))
            return empty_ocr

    def _assert_allowed_path(self, file_path: Path) -> None:
        """只准讀 data/messages 之下嘅檔案（防任意路徑；紙箱附件住 <box>/attachments/）。"""
        allowed_root = context.MESSAGES_DIR.resolve()

        # 判定：唔喺附件目錄之下 → 拒絕
        if allowed_root not in file_path.parents:
            raise PermissionError("path outside attachments dir: " + str(file_path))

    def _write_meta(self, image_path: Path, meta_data: dict[str, Any]) -> Path:
        """寫 meta json（同圖同目錄：<stem>.meta.json，atomic）。"""
        meta_path = image_path.with_suffix(".meta.json")
        temp_path = image_path.with_suffix(".meta.json.tmp")

        _ = temp_path.write_text(json.dumps(meta_data, ensure_ascii=False, indent=2), encoding="utf-8")
        _ = temp_path.replace(meta_path)

        return meta_path

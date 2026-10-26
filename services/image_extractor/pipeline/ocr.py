# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false, reportUnusedCallResult=false
#!/usr/bin/env python3
"""
ocr.py — 文字抽取（RapidOCR；首次呼叫才載入模型）。

上游：extractor（視覺層之後）
下游：rapidocr_onnxruntime／OpenCV（第三方）
"""

import time
from typing import Any

import cv2
import numpy as np
from rapidocr_onnxruntime import RapidOCR  # pyright: ignore[reportPrivateLocalImportUsage]

from .. import context

# 引擎單例（模型載入一次——同一進程反覆用）
_ocr_engine: RapidOCR | None = None


def _get_engine() -> RapidOCR:
    """取得 OCR 引擎（惰性建立）。"""
    global _ocr_engine

    if _ocr_engine is None:
        _ocr_engine = RapidOCR()

    return _ocr_engine


def read_ocr(image_array: np.ndarray | None) -> dict[str, Any]:
    """OCR 一張圖；回 {engine, text, lines, elapsed_ms}。"""
    ocr_info: dict[str, Any] = {
        "engine": "rapidocr_onnxruntime",
        "text": "",
        "lines": [],
        "elapsed_ms": 0,
    }

    # 判定：影像為空 → 空結果
    if image_array is None or image_array.size < 1:
        return ocr_info

    small_image = _resize_for_ocr(image_array)
    engine = _get_engine()

    start_time = time.perf_counter()
    result_list, _ = engine(small_image)
    elapsed_ms = int((time.perf_counter() - start_time) * 1000)

    ocr_info["elapsed_ms"] = elapsed_ms

    # 判定：冇偵測到文字 → 回空
    if not result_list:
        return ocr_info

    line_list: list[dict[str, Any]] = []

    for result_item in result_list:
        box_points = result_item[0]
        line_text = str(result_item[1])
        line_score = float(result_item[2])

        bbox = _box_to_bbox(box_points)

        line_list.append({
            "bbox": bbox,
            "text": line_text,
            "score": round(line_score, 4),
        })

    # 閱讀次序：先上而下，再左至右
    line_list.sort(key=lambda line_item: (line_item["bbox"][1], line_item["bbox"][0]))

    ocr_info["lines"] = line_list
    ocr_info["text"] = "\n".join(line_item["text"] for line_item in line_list)

    return ocr_info


def _resize_for_ocr(image_array: np.ndarray) -> np.ndarray:
    """長邊超過上限 → 等比縮細（實測快 2.7 倍、結果相若）。"""
    height, width = image_array.shape[:2]
    longest_side = max(height, width)

    # 判定：未超上限 → 原圖
    if longest_side <= context.OCR_MAX_SIDE_PX:
        return image_array

    scale = context.OCR_MAX_SIDE_PX / float(longest_side)

    return cv2.resize(image_array, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)


def _box_to_bbox(box_points: Any) -> list[int]:
    """四點座標 → [x, y, w, h]。"""
    point_list = np.array(box_points, dtype=float).reshape((-1, 2))

    min_x = int(np.min(point_list[:, 0]))
    min_y = int(np.min(point_list[:, 1]))
    max_x = int(np.max(point_list[:, 0]))
    max_y = int(np.max(point_list[:, 1]))

    return [min_x, min_y, max_x - min_x, max_y - min_y]

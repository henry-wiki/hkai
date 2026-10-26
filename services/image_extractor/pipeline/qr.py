# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false, reportUnusedCallResult=false
#!/usr/bin/env python3
"""
qr.py — QR／條碼偵測（OpenCV 內建；免額外套件）。

上游：extractor
下游：OpenCV（第三方）
"""

from typing import Any

import cv2
import numpy as np


def read_qr(image_array: np.ndarray | None) -> list[dict[str, Any]]:
    """偵測 QR；回 [{text, bbox}]。"""
    qr_list: list[dict[str, Any]] = []

    # 判定：影像為空 → 空清單
    if image_array is None or image_array.size < 1:
        return qr_list

    gray_image = cv2.cvtColor(image_array, cv2.COLOR_BGR2GRAY)
    detector = cv2.QRCodeDetector()

    is_decoded, decoded_list, points_list, _ = detector.detectAndDecodeMulti(gray_image)

    # 判定：冇解到 → 空清單
    if is_decoded is False:
        return qr_list

    point_array = np.array(points_list, dtype=float)

    for text_index, decoded_text in enumerate(decoded_list):
        # 判定：空白內容 → 跳過
        if decoded_text == "":
            continue

        qr_item: dict[str, Any] = {"text": str(decoded_text)}

        # 判定：有對應座標 → 記 bbox
        if text_index < len(point_array):
            qr_item["bbox"] = _points_to_bbox(point_array[text_index])

        qr_list.append(qr_item)

    return qr_list


def _points_to_bbox(point_list: np.ndarray) -> list[int]:
    """四點 → [x, y, w, h]。"""
    min_x = int(np.min(point_list[:, 0]))
    min_y = int(np.min(point_list[:, 1]))
    max_x = int(np.max(point_list[:, 0]))
    max_y = int(np.max(point_list[:, 1]))

    return [min_x, min_y, max_x - min_x, max_y - min_y]

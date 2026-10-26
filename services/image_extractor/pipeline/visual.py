# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false
#!/usr/bin/env python3
"""
visual.py — 視覺層資料（OpenCV）：亮度／對比／模糊值／主色。

上游：extractor（每張圖第二步；共用已解碼影像）
下游：OpenCV／numpy（第三方）
"""

from typing import Any

import cv2
import numpy as np

# 主色數量（k-means 群數）
DOMINANT_COLOR_COUNT = 3
# 主色取樣縮圖（64×64 已足夠穩定——減少 k-means 成本）
COLOR_SAMPLE_SIDE = 64


def read_visual_info(image_array: np.ndarray | None) -> dict[str, Any]:
    """由 BGR 影像計亮度／對比／模糊值／主色；空影像 → 空 dict。"""
    # 判定：影像為空 → 空
    if image_array is None or image_array.size < 1:
        return {}

    gray_image = cv2.cvtColor(image_array, cv2.COLOR_BGR2GRAY)

    brightness = float(np.mean(gray_image)) / 255.0
    contrast = float(np.std(gray_image)) / 128.0
    blur_score = float(cv2.Laplacian(gray_image, cv2.CV_64F).var())

    visual_info: dict[str, Any] = {
        "brightness": round(brightness, 4),
        "contrast": round(contrast, 4),
        "blur_score": round(blur_score, 2),
        "dominant_colors": _dominant_colors(image_array),
    }

    return visual_info


def _dominant_colors(image_array: np.ndarray) -> list[dict[str, Any]]:
    """k-means 取主色（hex＋佔比）。"""
    small_image = cv2.resize(
        image_array,
        (COLOR_SAMPLE_SIDE, COLOR_SAMPLE_SIDE),
        interpolation=cv2.INTER_AREA,
    )

    pixel_data = small_image.reshape((-1, 3)).astype(np.float32)
    cluster_count = min(DOMINANT_COLOR_COUNT, len(pixel_data))

    criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 20, 1.0)
    best_labels: Any = None
    _, label_data, center_data = cv2.kmeans(
        pixel_data,
        cluster_count,
        best_labels,
        criteria,
        3,
        cv2.KMEANS_PP_CENTERS,
    )

    label_list = label_data.flatten().astype(int)
    color_list: list[dict[str, Any]] = []

    for cluster_index in range(cluster_count):
        pixel_count = int(np.count_nonzero(label_list == cluster_index))

        # 判定：該群冇像素 → 跳過
        if pixel_count < 1:
            continue

        blue_value = int(center_data[cluster_index][0])
        green_value = int(center_data[cluster_index][1])
        red_value = int(center_data[cluster_index][2])
        share = round(pixel_count / len(label_list), 4)

        color_list.append({
            "hex": "#" + format(red_value, "02x") + format(green_value, "02x") + format(blue_value, "02x"),
            "share": share,
        })

    color_list.sort(key=lambda color_item: color_item["share"], reverse=True)

    return color_list

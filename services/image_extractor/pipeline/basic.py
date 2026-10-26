# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false
#!/usr/bin/env python3
"""
basic.py — 檔案層資料（Pillow）：尺寸／格式／模式／EXIF／sha256。

上游：extractor（每張圖第一步）
下游：Pillow（第三方）
"""

import hashlib
from pathlib import Path
from typing import Any

from PIL import Image

# EXIF 標籤（用官方 tag id——唔同廠商名稱有差異）
EXIF_DATETIME_ORIGINAL = 36867
EXIF_MAKE = 271
EXIF_MODEL = 272
EXIF_ORIENTATION = 274
EXIF_GPS_INFO = 34853


def read_basic_info(image_path: Path) -> dict[str, Any]:
    """讀檔案層資料；開唔到圖 → 只有檔名／大小。"""
    file_bytes = image_path.read_bytes()
    basic_info: dict[str, Any] = {
        "original_filename": image_path.name,
        "bytes": len(file_bytes),
        "sha256": hashlib.sha256(file_bytes).hexdigest(),
        "width": 0,
        "height": 0,
        "format": "",
        "mode": "",
        "exif": {},
    }

    try:
        with Image.open(image_path) as image_handle:
            basic_info["width"] = int(image_handle.width)
            basic_info["height"] = int(image_handle.height)
            basic_info["format"] = str(image_handle.format or "")
            basic_info["mode"] = str(image_handle.mode or "")
            basic_info["exif"] = _read_exif(image_handle)
    except OSError:
        return basic_info

    return basic_info


def _read_exif(image_handle: Image.Image) -> dict[str, Any]:
    """抽常用 EXIF；冇／壞 → 空 dict。"""
    exif_info: dict[str, Any] = {}

    try:
        exif_raw = image_handle.getexif()
    except (OSError, ValueError):
        return exif_info

    taken_at = exif_raw.get(EXIF_DATETIME_ORIGINAL, "")
    camera_make = exif_raw.get(EXIF_MAKE, "")
    camera_model = exif_raw.get(EXIF_MODEL, "")
    orientation = exif_raw.get(EXIF_ORIENTATION, "")

    # 判定：有拍攝時間 → 記
    if taken_at != "":
        exif_info["taken_at"] = str(taken_at)

    # 判定：有廠商 → 記
    if camera_make != "":
        exif_info["camera_make"] = str(camera_make)

    # 判定：有型號 → 記
    if camera_model != "":
        exif_info["camera_model"] = str(camera_model)

    # 判定：有方向 → 記
    if orientation != "":
        exif_info["orientation"] = int(str(orientation)) if str(orientation).isdigit() else str(orientation)

    gps_info = _read_gps(exif_raw)

    # 判定：有 GPS → 記（只記數值——唔入 history）
    if gps_info != {}:
        exif_info["gps"] = gps_info

    return exif_info


def _read_gps(exif_raw: Any) -> dict[str, float]:
    """EXIF GPS → 十進位經緯度；缺／壞 → 空 dict。"""
    try:
        gps_raw = exif_raw.get_ifd(EXIF_GPS_INFO)
    except (OSError, ValueError):
        return {}

    # 判定：冇 GPS ifd → 空
    if not isinstance(gps_raw, dict) or gps_raw == {}:
        return {}

    latitude = _gps_to_decimal(gps_raw.get(2), gps_raw.get(1))
    longitude = _gps_to_decimal(gps_raw.get(4), gps_raw.get(3))

    gps_info: dict[str, float] = {}

    # 判定：有緯度 → 記
    if latitude is not None:
        gps_info["latitude"] = latitude

    # 判定：有經度 → 記
    if longitude is not None:
        gps_info["longitude"] = longitude

    return gps_info


def _gps_to_decimal(coordinate_value: Any, reference_value: Any) -> float | None:
    """GPS 度分秒 → 十進位（南緯／西經為負）。"""
    # 判定：格式唔啱 → None
    if coordinate_value is None or len(coordinate_value) != 3:
        return None

    degrees = float(coordinate_value[0])
    minutes = float(coordinate_value[1])
    seconds = float(coordinate_value[2])

    decimal_value = degrees + (minutes / 60.0) + (seconds / 3600.0)
    reference_text = str(reference_value or "").upper()

    # 判定：南緯／西經 → 負值
    if reference_text in ("S", "W"):
        decimal_value = -decimal_value

    return round(decimal_value, 6)

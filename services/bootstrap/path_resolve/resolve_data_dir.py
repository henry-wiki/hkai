"""resolve_data_dir.py — 資料根（Path 型）。"""

from pathlib import Path

from .get_data_dir import get_data_dir


def resolve_data_dir() -> Path:
    """回傳資料根（Path 型——方便 / 拼接；值同 get_data_dir 一致）。"""
    return Path(get_data_dir())

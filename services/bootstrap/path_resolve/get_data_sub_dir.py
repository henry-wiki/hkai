"""get_data_sub_dir.py — 資料根之下嘅子路徑。"""

from .get_data_dir import get_data_dir


def get_data_sub_dir(relative_path: str) -> str:
    """回傳 {data_dir}/{relative_path}（對應 PHP get_data_sub_dir）。"""
    return get_data_dir() + "/" + relative_path

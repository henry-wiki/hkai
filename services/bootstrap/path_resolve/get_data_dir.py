"""get_data_dir.py — 資料根（對應 PHP sc_get_data_dir）。"""

import os

from ..env import get_env
from .resolve_host_suffix import resolve_host_suffix

# 資料根快取（空＝未解析；進程內固定——DATA_BASE_DIR／.env 啟動時已定）
_data_dir = ""


def get_data_dir() -> str:
    """回傳資料根（<data_dir>；首次呼叫解析並快取）。"""
    global _data_dir

    # 判定：已快取 → 直接回
    if _data_dir:
        return _data_dir

    # 每次都由 OS 環境直接讀（唔靠 init 先後——任何啟動路徑都一致）
    data_base_dir = os.environ.get("DATA_BASE_DIR", "").strip()

    app_vendor = str(get_env("APP_VENDOR") or "").strip()
    app_name = str(get_env("APP_NAME") or "").strip()

    data_dirs = []

    # 判定：三項齊 → {base}/{vendor}/{app}/data{頻道}（同 PHP 基礎根分支）
    if data_base_dir and app_vendor and app_name:
        data_dirs.append(data_base_dir)
        data_dirs.append(app_vendor)
        data_dirs.append(app_name)
        data_dirs.append("data" + resolve_host_suffix())
    else:
        # 延遲 import（站台根喺 bootstrap/path.py——避開 path ↔ path_resolve 循環）
        from ..path import REPO_ROOT

        data_dirs.append(str(REPO_ROOT))
        data_dir_name = str(get_env("DATA_DIR") or "").strip() or "data"

        data_dirs.append(data_dir_name)

    _data_dir = "/".join(data_dirs).replace("\\", "/").rstrip("/")

    return _data_dir

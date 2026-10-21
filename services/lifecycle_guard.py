"""lifecycle_guard.py — 程序生命契約（共用）

規則：
- 程序只准活喺「設定檔存在」＋「BUILD 未變」兩個條件之下；任一失效 → 呼叫端記錄後自我退出。
- BUILD 位置＝站台根（含 web／services 的資料夾）；值由 lab build 寫入（YYYYMMDD.HHMMSS）。
- 本模組唔知引擎細節：只回傳判斷結果，退出方式由呼叫端決定。
"""

from pathlib import Path


def find_site_root(start_path) -> Path:
    """由 runtime／conf 檔位置向上搵站台根（同時有 web 與 services 的資料夾）。"""
    current_path = Path(start_path).resolve()

    # 判定：傳入係檔案 → 由所在目錄開始
    if current_path.is_file():
        current_path = current_path.parent

    # 最多向上四層（data／web/sc_storage/hkai 都夠）
    for _ in range(5):
        # 判定：兩個目錄都在 → 就係站台根
        if (current_path / "web").is_dir() and (current_path / "services").is_dir():
            return current_path

        parent_path = current_path.parent
        # 判定：到頂 → 回傳起點目錄（讀唔到 BUILD 就當空）
        if parent_path == current_path:
            break
        current_path = parent_path

    return Path(start_path).resolve().parent


def read_build_id(site_root: Path) -> str:
    """讀 BUILD 值（缺檔／讀唔到 → 空字串）。"""
    build_file = Path(site_root) / "BUILD"

    try:
        return build_file.read_text(encoding="utf-8", errors="replace").strip()
    except OSError:
        return ""


class build_watch_class:
    """BUILD 監看器：開機記住基準值，之後每次 check() 回報有冇變。"""

    def __init__(self, site_root: Path):
        self._site_root = Path(site_root)
        self._baseline = read_build_id(self._site_root)

    def check(self) -> dict:
        """回傳 {changed, previous, current}；基準會跟住更新（避免重複觸發）。"""
        current_build_id = read_build_id(self._site_root)
        previous_build_id = self._baseline

        # 判定：未變（含兩邊都空）→ 冇事
        if current_build_id == previous_build_id:
            return {"changed": False, "previous": previous_build_id, "current": current_build_id}

        self._baseline = current_build_id
        return {"changed": True, "previous": previous_build_id, "current": current_build_id}

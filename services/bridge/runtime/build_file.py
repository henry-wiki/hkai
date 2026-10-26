#!/usr/bin/env python3
"""
build_file.py — BUILD 檔讀取＋變更監視（bridge 專用；只依賴標準庫，可獨立搬走）

- read()        讀 BUILD 值（缺檔／讀唔到 → 空字串）
- initialize()  記住基準值（server 啟動時一次）
- changed()     對比基準（有變即更新基準；BUILD 唔存在＝當冇變）

上游：server._heartbeat_loop()
下游：context.BUILD_FILE
"""

from ..context import BUILD_FILE

# BUILD 基準值（initialize 時記住——之後每次 changed 對比）
_baseline = ""


def read() -> str:
    """讀 BUILD 值（缺檔／讀唔到 → 空字串）。"""
    try:
        return BUILD_FILE.read_text(encoding="utf-8", errors="replace").strip()
    except OSError:
        return ""


def initialize() -> None:
    """記住目前 BUILD 值做基準（server 啟動時呼叫一次）。"""
    global _baseline
    _baseline = read()


def changed() -> dict[str, object]:
    """回傳 {changed, previous, current}；有變即更新基準。"""
    global _baseline
    current_build_id = read()
    previous_build_id = _baseline

    # 判定：BUILD 唔存在（讀唔到）→ 當冇變（唔更新基準——檔案返嚟先再對比）
    if current_build_id == "":
        return {"changed": False, "previous": previous_build_id, "current": current_build_id}

    # 判定：同基準一樣 → 冇變
    if current_build_id == previous_build_id:
        return {"changed": False, "previous": previous_build_id, "current": current_build_id}

    _baseline = current_build_id

    return {"changed": True, "previous": previous_build_id, "current": current_build_id}

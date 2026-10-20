import json
import os
from pathlib import Path
from typing import Optional


def get_tail_content(
    path: Path | str, tail: int | None = 0, encoding: str = "utf-8"
) -> str:
    p = Path(path)

    try:
        if not tail or tail <= 0:
            return p.read_text(encoding=encoding, errors="replace")

        with p.open("rb") as f:
            # os.SEEK_END 就是 2，代表移動到檔尾
            file_size = f.seek(0, os.SEEK_END)
            if file_size == 0:
                return ""

            # 針對中文字 (UTF-8 3~4 bytes) 提供足夠的緩衝區
            buffer_size = min(file_size, tail * 4 + 64)
            f.seek(-buffer_size, os.SEEK_END)
            raw_bytes = f.read(buffer_size)

        # 完美解碼中文，並切出尾端指定字數
        text = (
            raw_bytes.decode(encoding, errors="ignore")
            .replace("\r\n", "\n")
            .replace("\r", "\n")
        )
        return text[-tail:]

    except (FileNotFoundError, IsADirectoryError, PermissionError):
        return ""


def read_json(path: Optional[Path | str]) -> Optional[dict]:
    """防禦性讀取 JSON，失敗回 None，唔拋例外。"""
    if path is None:
        return None
    p = Path(path)
    if not p.is_file():
        return None
    try:
        with open(p, "r", encoding="utf-8") as f:
            data = json.load(f)
            return data if isinstance(data, dict) else None
    except Exception:  # noqa: BLE001
        return None


def update_json(path: Path | str, data: dict, encoding: str = "utf-8") -> bool:
    """讀取現有 JSON → 合併新 data → 寫回。

    邏輯流程：
    1. 讀取現有檔案（不存在則建立空 dict）
    2. 合併新 data（覆蓋指定欄位）
    3. 寫回檔案

    回傳：成功 True / 失敗 False
    """
    p = Path(path)

    # 步驟 1：讀取現有
    existing = {}
    if p.exists():
        try:
            existing = json.loads(p.read_text(encoding=encoding))
            if not isinstance(existing, dict):
                existing = {}
        except Exception:
            existing = {}

    # 步驟 2：合併
    merged = {**existing, **data}

    # 步驟 3：寫回
    try:
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(json.dumps(merged, indent=2, ensure_ascii=False), encoding=encoding)
        return True
    except Exception:
        return False

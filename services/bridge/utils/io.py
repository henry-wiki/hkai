import json
import os
from collections.abc import Mapping
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


def read_json(path: Optional[Path | str]) -> Optional[dict[str, object]]:
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


def write_json_atomic(path: Path | str, data: Mapping[str, object], encoding: str = "utf-8") -> bool:
    """全量寫入 JSON：path.tmp → os.replace 原子替換。

    失敗 → 刪 .tmp ＋ 記 error log；回傳 成功 True／失敗 False。
    """
    target_path = Path(path)
    tmp_path = Path(str(target_path) + ".tmp")

    try:
        target_path.parent.mkdir(parents=True, exist_ok=True)
        content = json.dumps(data, indent=2, ensure_ascii=False)
        tmp_path.write_text(content, encoding=encoding, newline="\n")
        os.replace(tmp_path, target_path)
        return True
    except Exception as exc:  # noqa: BLE001
        # 判定：.tmp 仲在 → 刪除（避免留殘檔）
        try:
            if tmp_path.exists():
                tmp_path.unlink()
        except OSError:
            pass

        # 記 error log（logger 問題唔可以蓋過原本錯誤）
        try:
            from bootstrap import sc_logger

            sc_logger.log("write_json_atomic failed: " + str(target_path) + " (" + str(exc) + ")")
        except Exception:
            pass

        return False

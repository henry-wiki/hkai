import json
import os
from collections.abc import Mapping
from pathlib import Path
from typing import cast


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
            _ = f.seek(-buffer_size, os.SEEK_END)
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

# 讀檔尾（唔讀全檔）→ 回最後一個非空行；無內容回空字串
def get_last_non_empty_line(path: Path | str, tail_chars: int = 4096) -> str:
    tail_text = get_tail_content(path, tail=tail_chars)

    # 先去頭尾空白／空行（一次搞定——唔使逐行反轉掃）
    trimmed_text = tail_text.strip()

    # 判定：全空 → 空字串
    if trimmed_text == "":
        return ""

    last_line = trimmed_text.splitlines()[-1]

    return last_line.strip()


# 防禦性讀取 JSON（失敗回 None，唔拋例外）——sc_config 用
def read_json(path: Path | str | None) -> dict[str, object] | None:
    """讀 JSON 檔（失敗／非物件 → None）。"""
    # 判定：冇路徑 → None
    if path is None:
        return None

    json_path = Path(path)

    # 判定：檔案唔存在 → None
    if json_path.is_file() is False:
        return None

    try:
        with open(json_path, "r", encoding="utf-8") as file_handle:
            data = cast("object", json.load(file_handle))
    except Exception:  # noqa: BLE001
        return None

    # 判定：非物件 → None
    if isinstance(data, dict) is False:
        return None

    return cast("dict[str, object]", data)


# 全量寫入 JSON：path.tmp → os.replace 原子替換（失敗回 False，唔拋例外）
def write_json_atomic(path: Path | str, data: Mapping[str, object], encoding: str = "utf-8") -> bool:
    target_path = Path(path)
    tmp_path = Path(str(target_path) + ".tmp")

    try:
        target_path.parent.mkdir(parents=True, exist_ok=True)
        content = json.dumps(data, indent=2, ensure_ascii=False)
        _ = tmp_path.write_text(content, encoding=encoding, newline=chr(10))
        os.replace(tmp_path, target_path)
        return True
    except Exception:  # noqa: BLE001
        try:
            if tmp_path.exists():
                tmp_path.unlink()
        except OSError:
            pass
        return False

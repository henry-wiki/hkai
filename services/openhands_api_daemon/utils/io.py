import os
from pathlib import Path


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

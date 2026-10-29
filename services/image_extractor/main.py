#!/usr/bin/env python3
"""
main.py — image_extractor CLI 入口（工作目錄＝紙箱）。

用法：
    python -m image_extractor.main run <box_dir>      處理整箱（逐張；已有 <圖>.meta.json 跳過）
    python -m image_extractor.main stop <box_dir>     終止（連子進程）＋交還認領
    python -m image_extractor.main status <box_dir>   印出整體＋逐張狀態（JSON）

上游：bridge（POST /image_extract/start｜stop 之後 spawn 本 CLI）
下游：extractor、box_store、runtime（pidfile／sc_logger）
"""

import json
import os
import signal
import sys
from pathlib import Path
from types import FrameType
from typing import cast

from . import box_store
from .extractor import extractor_class
from .runtime import pidfile, sc_logger

USAGE_TEXT = "usage: python -m image_extractor.main <run|stop|status> <box_dir>"


def main(argv: list[str]) -> int:
    """CLI 分派；回 exit code。"""
    # 判定：參數不足 → 印用法
    if len(argv) < 3:
        print(USAGE_TEXT, file=sys.stderr)
        return 2

    command = argv[1]
    box_dir = Path(argv[2]).resolve()

    # 判定：run → 執行抽取
    if command == "run":
        return _run(box_dir)

    # 判定：stop → 終止＋交還認領
    if command == "stop":
        return _stop(box_dir)

    # 判定：status → 印狀態
    if command == "status":
        return _status(box_dir)

    print(USAGE_TEXT, file=sys.stderr)

    return 2


def _run(box_dir: Path) -> int:
    """執行整箱（認領＋pid 檔＋訊號處理）。"""
    self_pid = os.getpid()

    # 判定：已有活主人 → 唔重複做
    if pidfile.claim_pid(box_dir, self_pid) is False:
        sc_logger.log("BUSY", "box already claimed: " + str(box_dir))

        return 0

    extractor = extractor_class(box_dir)

    def handle_signal(signal_number: int, frame: FrameType | None) -> None:
        """收到終止訊號 → 交還認領後即刻離開。"""
        _ = frame
        sc_logger.log("SIGNAL", "received signal " + str(signal_number))
        pidfile.release_pid(box_dir, self_pid)
        os._exit(0)

    _ = signal.signal(signal.SIGTERM, handle_signal)
    _ = signal.signal(signal.SIGINT, handle_signal)

    try:
        success_count = extractor.run()
    finally:
        pidfile.release_pid(box_dir, self_pid)

    sc_logger.log("JOB_FINISHED", "success=" + str(success_count), {"box_dir": str(box_dir)})

    return 0


def _stop(box_dir: Path) -> int:
    """停止：殺進程樹＋交還認領。"""
    pid = pidfile.read_pid(box_dir)
    killed = pidfile.terminate_tree(pid)
    pidfile.remove_pid(box_dir)

    sc_logger.log("STOP", "killed=" + str(killed), {"pid": pid, "box_dir": str(box_dir)})

    print(json.dumps({"ok": True, "killed": killed, "pid": pid}, ensure_ascii=False))

    return 0


def _status(box_dir: Path) -> int:
    """印箱內抽取狀態（meta.status＋逐張有無結果）。"""
    meta_data = box_store.read_meta(box_dir)
    status_data: dict[str, object] = {}

    # 判定：meta 讀唔到 → 報錯
    if meta_data is None:
        status_data["state"] = "box_invalid"
        print(json.dumps(status_data, ensure_ascii=False, indent=2))

        return 1

    job_files = box_store.list_attachment_files(box_dir, meta_data)
    file_list: list[dict[str, object]] = []

    for job_file_item in job_files:
        file_path = Path(str(job_file_item.get("path", "")))
        result_path = file_path.with_suffix(".meta.json")

        file_list.append(
            {
                "idx": int(job_file_item.get("idx", 0)),
                "file": file_path.name,
                "done": result_path.is_file(),
            }
        )

    status_data["message_id"] = int(cast(int, meta_data.get("message_id", 0)))
    status_data["state"] = str(meta_data.get("status", ""))
    status_data["total"] = len(file_list)
    status_data["done"] = len([item for item in file_list if item.get("done") is True])
    status_data["files"] = file_list

    print(json.dumps(status_data, ensure_ascii=False, indent=2))

    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))

#!/usr/bin/env python3
"""
main.py — image_extractor CLI 入口。

用法：
    python -m image_extractor.main run <job_dir>      處理整份 job（逐張）
    python -m image_extractor.main stop <job_dir>     終止（連子進程）＋標記 cancelled
    python -m image_extractor.main status <job_dir>   印出整體＋逐張狀態（JSON）

上游：bridge（POST /image_extract/start｜stop 之後 spawn 本 CLI）
下游：extractor、job_store、runtime（pidfile／sc_logger）
"""

import json
import os
import signal
import sys
from pathlib import Path
from types import FrameType
from typing import cast

from . import job_store
from .extractor import extractor_class
from .runtime import pidfile, sc_logger

USAGE_TEXT = "usage: python -m image_extractor.main <run|stop|status> <job_dir>"


def main(argv: list[str]) -> int:
    """CLI 分派；回 exit code。"""
    # 判定：參數不足 → 印用法
    if len(argv) < 3:
        print(USAGE_TEXT, file=sys.stderr)
        return 2

    command = argv[1]
    job_dir = Path(argv[2]).resolve()

    # 判定：run → 執行抽取
    if command == "run":
        return _run(job_dir)

    # 判定：stop → 終止＋標記
    if command == "stop":
        return _stop(job_dir)

    # 判定：status → 印狀態
    if command == "status":
        return _status(job_dir)

    print(USAGE_TEXT, file=sys.stderr)

    return 2


def _run(job_dir: Path) -> int:
    """執行整份 job（pid 檔＋訊號處理）。"""
    job_dir.mkdir(parents=True, exist_ok=True)
    pidfile.write_pid(job_dir, os.getpid())

    extractor = extractor_class(job_dir)

    def handle_signal(signal_number: int, frame: FrameType | None) -> None:
        """收到終止訊號 → 標記 cancelled 後即刻離開。"""
        _ = frame
        sc_logger.log("SIGNAL", "received signal " + str(signal_number))
        extractor.cancel()
        pidfile.remove_pid(job_dir)
        os._exit(0)

    _ = signal.signal(signal.SIGTERM, handle_signal)
    _ = signal.signal(signal.SIGINT, handle_signal)

    try:
        success_count = extractor.run()
    finally:
        pidfile.remove_pid(job_dir)

    sc_logger.log("JOB_FINISHED", "success=" + str(success_count), {"job_dir": str(job_dir)})

    return 0


def _stop(job_dir: Path) -> int:
    """停止：殺進程樹 → 標記 cancelled（已完成者不動）。"""
    pid = pidfile.read_pid(job_dir)
    killed = pidfile.terminate_tree(pid)

    extractor = extractor_class(job_dir)
    extractor.cancel()

    sc_logger.log("STOP", "killed=" + str(killed), {"pid": pid, "job_dir": str(job_dir)})

    print(json.dumps({"ok": True, "killed": killed, "pid": pid}, ensure_ascii=False))

    return 0


def _status(job_dir: Path) -> int:
    """印整體＋逐張狀態。"""
    job_data = job_store.read_job(job_dir)
    status_list = job_store.list_file_statuses(job_dir, job_data)
    overall_state = job_store.derive_overall_state(status_list)

    status_result = {
        "message_id": cast(int, job_data.get("message_id", 0)),
        "state": overall_state,
        "done": len([item for item in status_list if item.get("state") == "done"]),
        "total": len(status_list),
        "files": status_list,
    }

    print(json.dumps(status_result, ensure_ascii=False, indent=2))

    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))

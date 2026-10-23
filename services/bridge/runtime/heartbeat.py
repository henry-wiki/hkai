#!/usr/bin/env python3
"""
heartbeat.py — 心跳（生存證明＋閒置自殺；bridge 專用，只依賴標準庫，可獨立搬走）

對外入口：
- start(interval_sec, idle_exit_sec, is_busy)
      登記本程序 → 起背景執行緒：
      ① 每 interval_sec 寫一次心跳（唔會停）
      ② 每次跳都對比活動檔年齡——超過 idle_exit_sec 且冇進行中串流 → 自殺
- stop()  停執行緒＋清心跳檔

心跳寫檔交 runtime/process_status.py；活動時間交 runtime/activity.py。
"""

import os
import threading
import time

from ..context import PROGRAM_LABEL, PROGRAM_NAME

# 背景執行緒與停止訊號（start 建、stop 收）
_beat_thread = None
_stop_event = threading.Event()

# 進程啟動時間（活動檔未建立時，用佢做閒置基準）
_started_at = time.time()


def start(interval_sec: float = 1.0, idle_exit_sec: int = 0, is_busy=None) -> None:
    """開始心跳：登記＋背景執行緒（每 interval 秒 beat；閒置過久 → 自殺）。"""
    global _beat_thread, _started_at

    from . import process_status

    _started_at = time.time()
    process_status.register(os.getpid())

    _stop_event.clear()
    _beat_thread = threading.Thread(
        target=_beat_loop,
        args=(interval_sec, idle_exit_sec, is_busy),
        name="heartbeat_beat",
        daemon=True,
    )
    _beat_thread.start()


def _beat_loop(interval_sec: float, idle_exit_sec: int, is_busy) -> None:
    """背景心跳：每輪寫檔，再判斷閒置自殺。"""
    from . import activity, process_status, sc_logger

    while _stop_event.is_set() is False:
        process_status.beat()

        # 判定：有設閒置上限 → 對比活動檔年齡
        if idle_exit_sec > 0:
            idle_sec = activity.age_sec()

            # 判定：活動檔未建立（inf）→ 由進程啟動起計
            if idle_sec == float("inf"):
                idle_sec = time.time() - _started_at

            has_active_stream = is_busy() if is_busy is not None else False

            # 判定：活動過期＋冇進行中串流 → 自殺（連清心跳／活動檔）
            if idle_sec > idle_exit_sec and has_active_stream is False:
                # 訊息用（inf 冇活動檔——唔可以 int()）
                idle_sec_text = "inf" if idle_sec == float("inf") else str(int(idle_sec))
                sc_logger.log("idle " + idle_sec_text + "s with no active stream, heartbeat exiting")
                process_status.unregister(os.getpid())
                activity.clear()
                os._exit(0)

        time.sleep(interval_sec)


def beat() -> None:
    """寫一次心跳（手動／測試用——背景執行緒已每秒自動跳）。"""
    from . import process_status

    process_status.beat()


def stop() -> None:
    """停止心跳：停背景執行緒，再清自己的心跳檔。"""
    global _beat_thread

    from . import process_status

    _stop_event.set()

    if _beat_thread is not None:
        _beat_thread.join(timeout=2.0)
        _beat_thread = None

    process_status.unregister(os.getpid())

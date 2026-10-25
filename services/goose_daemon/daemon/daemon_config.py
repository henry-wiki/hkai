#!/usr/bin/env python3
"""
daemon_config.py — daemon 設定集中地（唯一入口；唔散落 manager／worker）

責任：daemon 行為常數（節奏／閒置／記憶體守衛）——硬設於此，唔經 runtime.json

上游：daemon_manager／memory_guard
下游：無（純常數——唔讀檔、唔寫檔）
"""

# 主迴圈節奏（秒——輪詢 msg_conf／回收 worker 嘅延遲上限）
MAIN_LOOP_INTERVAL_SEC = 1.0

# 子樹記憶體警告門檻（MB——goose 鏈 RSS 總和）
CHILD_MEMORY_WARNING_LIMIT_MB = 3072

# 子樹記憶體硬上限（MB——超過即中止，唔理增長率）
CHILD_MEMORY_ABSOLUTE_LIMIT_MB = 6144

# 泄漏判定（秒＋MB——警告區內窗口增長達門檻即判泄漏）
CHILD_MEMORY_GROWTH_WINDOW_SEC = 30
CHILD_MEMORY_GROWTH_LIMIT_MB = 512

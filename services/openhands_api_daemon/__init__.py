#!/usr/bin/env python3
"""
openhands_api_daemon 套件——引擎接駁版（全 class、單一職責、singleton 統一入口）。

    main.py                    入口（薄）：load_daemon_conf_data() + daemon_manager_class.run() 一行呼叫
    daemon/
        daemon_manager.py     daemon_manager_class(singleton)——生命週期調度：load_daemon_conf_data() + 主迴圈（pid 驗證/重載配置/掃描處理）
        worker.py             worker 子程序入口（冷路徑 run_worker／暖路徑 run_warm_worker）
    msg/
        msg_log.py             msg_log_class(singleton)——msg_log 檔讀寫（外殼 JSONL＋完成/錯誤標記常數）
        msg_main.py            msg_main_class(singleton)——msg 檔路徑／掃描／jobs／msg_conf 讀取／msg_status 讀寫
        msg_processor.py       msg_processor_class(singleton)——查詢處理：子程序執行＋連線等待
        msg_stdout.py          msg_stdout_class(singleton)——stdout 純紀錄檔讀寫
        msg_claim.py           msg_claim_class(singleton)——處理認領（原子）
        msg_state_machine.py   msg_state_machine_class(singleton)——FSM（唯一 msg_status 寫入者；fsm_map.json 契約）
        msg_events.py          event 常數集中檔（與 sc_config/hkai/fsm_map.json 同步）
    engine/
        auth_setup.py          api_key_loader_class(singleton)——API keys 載入環境變數（引擎子程序由此取 key）
        openhands_api_entry.py         openhands_api_entry_class(singleton)——執行入口（冷／暖；引擎 stream-json 翻譯）
    utils/
        process_helper.py      process_helper_class(singleton)——子程序安全終止
        timer.py               timer_class(singleton)——統一時間格式輸出
        io.py                  get_tail_content()
    runtime/
        sc_logger.py           setup()——執行日誌（error.log／audit.log）；log()——一般記錄
        process_list.py        append_spawn()——程序台帳（append-only JSONL）
        process_status.py      initialize/register/beat_all/unregister——程序自我心跳
"""

from pathlib import Path

# 套件根目錄（openhands_api_daemon/ 所在位置）
BASE_DIR = Path(__file__).resolve().parent

# runtime 目錄（log 等運行時資料）
RUNTIME_DIR = BASE_DIR / "runtime"

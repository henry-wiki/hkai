#!/usr/bin/env python3
"""
aider_daemon 套件——aider 熱 daemon 的模組化版本（全 class、單一職責、singleton 統一入口）。

    daemon/
        daemon_manager.py     daemon_manager_class(singleton)——生命週期調度：load_daemon_conf_data() + 主迴圈（pid 驗證/心跳自殺/重載配置/掃描處理）
        main.py               入口（薄）：load_daemon_conf_data() + daemon_manager_class.run() 一行呼叫
    msg/
        msg_log.py             msg_log_class(singleton)——msg_log 檔讀寫（外殼 JSONL＋完成/錯誤標記常數）
        msg_main.py            msg_main_class(singleton)——msg 檔路徑／掃描／jobs／msg_conf 讀取／msg_status 讀寫
        msg_processor.py       msg_processor_class(singleton)——查詢處理：重試迴圈＋子程序執行＋連線等待
        msg_stdout.py          msg_stdout_class(singleton)——stdout 純紀錄檔讀寫
        msg_claim.py           msg_claim_class(singleton)——處理認領（原子）
    engine/
        aider_entry.py         aider_entry_class(singleton)——執行入口（冷／暖；spawn aider_daemon.acp 子程序）
        acp_client.py          acp_client_class(singleton)——ACP 協定客戶端（stdio——拉 aider_daemon.acp）
        agent_history.py       agent_history_class(singleton)——我方 DB 對話 → agent 歷史（隨 session/new 帶入）
    acp/                   ACP server 側（aider 引擎接駁——獨立小程序；spawn target：python -m aider_daemon.acp.main）
        main.py               入口（薄）：sc_logger.setup() → asyncio.run(run_agent(agent()))
        core.py               ACP agent 本體（思考／回答／用量串流＋權限卡）
        permission.py         🧩 可拆模組：等確認（aider confirm ↔ ACP request_permission）
    adapter/
        auth_setup.py         api_key_loader_class(singleton)——API keys 載入環境變數
    utils/
        process_helper.py      process_helper_class(singleton)——子程序安全終止
        timer.py               timer_class(singleton)——統一時間格式輸出
    runtime/
        sc_logger.py           setup()——執行日誌（error.log／audit.log；daemon＋acp 共用）；log()——一般記錄
"""

from pathlib import Path

# 套件根目錄（aider_daemon/ 所在位置）
BASE_DIR = Path(__file__).resolve().parent

# runtime 目錄（log 等運行時資料）
RUNTIME_DIR = BASE_DIR / "runtime"

#!/usr/bin/env python3
"""
goose_daemon 套件——goose agent接駁版（單一職責模組；共用層經 context 取單例）。

    main.py                    入口（薄）：process.init → 心跳清潔工 → daemon.initialize() → run()
    context.py                 橋接層（單例入口＋常數；兩邊共用）
    daemon/                    daemon 側（只管工單／工人）
        daemon_config.py      daemon 行為常數（節奏／記憶體門檻）
        daemon_manager.py     主迴圈（設定／派工／回收／閒置）
        job_scanner.py        polling 工單表（唯讀掃描＋可派判斷）
        job_scheduler.py      工單排程（dispatch／開 worker／回收）
        job_signal.py         工單完成訊號（job_done → 回空閒）
        job_exit.py           經理收場（先禮後兵；工人走清才自殺）
        roster.py             工人名冊（add／remove／get_worker_entries；零決策）
        watchdog.py           工人看門狗（巡邏／判卡死／殺／死亡收尾）
    worker/                    worker 側（工人自己＋佢呼叫嘅嘢）
        runner.py             工人主迴圈（run——收工單逐張做）
        job_executor.py       執行一張工單（agent呼叫＋狀態機＋收尾）
        job_stdout.py         stdout 純紀錄檔
        message_log.py        msg_log 檔讀寫（外殼 JSONL）
        message_events.py     event 常數集中檔
        memory_guard.py       子程序記憶體守衛
        report.py             worker → daemon 生命週期上報（status_queue）
        agent/
            entry.py       goose 執行入口（preload／execute_job）
            session.py         我們契約 ↔ ACP（回呼／權限卡／事件）
            prepare.py     每問前置（goose 環境／hints／provider 檔）
            preflight.py          開工前預檢（白名單／key／執行檔／目錄）
            checkpoint.py         對話同步快照（讀／寫）
            conversation_sync.py  goose sessions.db 同步
            chat_mode.py   UI 模 → agent原生模式對映
            paths.py     goose 私有 context（路徑／provider 表）
            exceptions.py   整張工單失敗收尾
            acp_client.py         ACP client（JSON-RPC／stdio）
            acp_session_client.py SDK Client 介面實作
    msg/                       訊息域（檔案契約——兩邊共用）
        message_context.py    msg_* 檔名契約（type → 副檔名）
        message_store.py      msg 檔路徑／掃描／msg_conf／msg_status
        message_claim.py      原子認領
        message_status.py     FSM（唯一 msg_status 寫入者）
    utils/
        io.py                 檔尾讀取／最後非空行／JSON 讀寫
        timer.py              時間格式
    runtime/                   共用層（daemon／worker／agent）
        identity.py           程序身份常數（名／標籤單一來源）
        sc_logger.py          日誌（setup／log／write_early_error_log）
        process.py            程序身份＋台帳 record_spawn()
        heartbeat.py          daemon 心跳
        heartbeat_worker.py   worker 心跳（母親監視＋abort）
        heartbeat_agent.py   agent子程序心跳（worker 代寫）
        lifecycle.py          生死契約（exit／exit_children／exit_self／exit_child）
        sc_config.py          runtime.json 讀取
        sc_env.py             .env 讀取＋API key 注入
        build_file.py         BUILD 監看
        single_instance.py    單一實例檢查
"""

from pathlib import Path

# 套件根目錄（goose_daemon/ 所在位置）
BASE_DIR = Path(__file__).resolve().parent

# runtime 目錄（log 等運行時資料）
RUNTIME_DIR = BASE_DIR / "runtime"

# 站台根（BASE_DIR＝services/goose_daemon/，向上兩層）
ROOT_DIR = BASE_DIR.parent.parent

# 站台資料目錄（站台根 data/——所有執行產物集中喺呢度）
DATA_DIR = ROOT_DIR / "data"

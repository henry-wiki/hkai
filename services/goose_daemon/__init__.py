#!/usr/bin/env python3
"""
goose_daemon 套件——goose 引擎接駁版（全 class、單一職責、singleton 統一入口）。

    main.py                    入口（薄）：initialize() + daemon_manager_class.run() 一行呼叫
    context.py                 橋接層（單例入口＋常數；兩邊共用）
    daemon/                    daemon 側（只管工單／工人）
        daemon_config.py      daemon 行為常數（節奏／記憶體門檻）
        daemon_manager.py     主迴圈（設定／派工／回收／閒置）
        job_scheduler.py      工單排程（dispatch／建立／搵空閒／has_worker／回收）
        roster.py             工人名冊（add／remove／get_worker_entries；零決策）
        watchdog.py           工人看門狗（巡邏／判卡死／殺／死亡收尾）
        job_signal.py         工單完成訊號（job_done → 回空閒）
        job_scanner.py        polling 工單表（唯讀掃描＋可派判斷）
    worker/                    worker 側（工人自己＋佢呼叫嘅嘢）
        runner.py             工人主迴圈（run——收工單逐張做）
        identity.py           工人身份文件（啟動時套入 context）
        job_executor.py       執行一張工單（子程序執行＋連線等待＋收尾）
        message_log.py        msg_log 檔讀寫（外殼 JSONL）
        job_stdout.py         stdout 純紀錄檔
        message_events.py     event 常數集中檔
        memory_guard.py       子程序記憶體守衛（含監控 thread）
        engine/
            engine_entry.py        goose 執行入口（ACP）
            acp_client.py         ACP client（JSON-RPC／stdio）
            conversation_sync.py goose sessions.db 同步
            engine_chat_mode.py   UI 模 → 引擎原生模式對映
            engine_context.py     goose 引擎私有 context（GOOSE_PATH_ROOT／provider 表／輪詢間隔）
            preflight.py          開工前預檢（model 白名單／key／執行檔／專案目錄）
    msg/                       訊息域（檔案契約——兩邊共用）
        message_store.py      msg 檔路徑／掃描／msg_conf／msg_status
        message_claim.py      原子認領
        message_status.py     FSM（唯一 msg_status 寫入者）
    utils/
        io.py                 檔尾讀取／最後非空行／JSON 讀寫
        timer.py              時間格式
    runtime/                   兩邊共用
        sc_logger.py          日誌
        process.py            程序身份＋台帳 record_spawn()
        heartbeat.py          心跳檔＋背景 thread
        lifecycle.py          生死契約（exit／exit_children／exit_self／exit_child）
        sc_config.py          runtime.json 讀取
        sc_env.py             .env 讀取＋API key 注入
        build_file.py         BUILD 監看
        presence.py           活躍標記（有工人即 touch；閒置判斷＋自殺）
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

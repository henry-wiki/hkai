#!/usr/bin/env python3
"""
daemon_manager.py

daemon_manager_class 單例——所有「動作(action)」集中於此（生命週期調度）。

主迴圈（main.py 只負責一行呼叫，不重複寫判斷邏輯——單一事實來源）：

    load_config()               重新解析 runtime.json（無副作用）
    run()                       主迴圈（單一實例驗證 → 心跳自殺 → 重載配置 → 掃描處理 → 休眠）

未來若要新增動作（例如：強制重啟、清快取、重載 daemon_conf……）一律加在這裡。
"""

import os
import time

from ..context import PROGRAM_NAME, RUNTIME_CONFIG_FILE
from ..runtime import (
    heartbeat,
    lifecycle,
    presence,
    sc_config,
    sc_env,
    sc_logger,
    single_instance,
)
from . import (
    daemon_config,
    job_scanner,
    job_exit,
    job_scheduler,
    job_signal,
    roster,
    watchdog,
)


class daemon_manager_class:
    """
    daemon_manager_class 單例（整個程序只有一個實例——get_instance() 取得）。

    ===== 為什麼需要「自殺機制」（重要——請保留這份理解，日後可能忘記）=====
    1. 這個 daemon 是 PHP 冷啟動的「獨立背景程序」（popen 拉起），
       它與任何網頁請求、瀏覽器頁面都沒有生命週期連結。
    2. 因此：瀏覽器關閉、使用者離開頁面、前端停止輪詢——
       daemon 都不會因此結束，會「永遠常駐」在背景，持續佔用記憶體。
    3. 解法：用「心跳檔(heartbeat)」做無人使用的訊號——
       - PHP 端每次活動（前端輪詢 health / 發送訊息 Send）都會更新心跳檔的修改時間
       - daemon 每輪檢查「心跳檔最後更新距今幾秒」；超過自殺閾值
         （舊設定 daemon_heartbeat_self_kill_sec，預設 30 秒——現已移除，唔再用）
    4. 這是「按需常駐(on-demand)」設計：需要時（下次 Send / health）PHP 會重新拉起，
       所以自殺是安全的——不會造成功能消失，只是回收閒置資源。
    5. 心跳檔路徑與閾值都來自 runtime.json（PHP 單一來源控制，見 get_daemon_conf_data()）。
    ====================================================================

    ===== 2026-09 調整（現行行為——與上述自殺機制不同）=====
    daemon 改為「長駐＋集中管理」：
    - 心跳過期自殺機制已移除（唔再檢查舊心跳檔 mtime）
    - 由 daemon 統一調度暖 worker（數量不限；同對話一次一條）
    - 殺／留集中由 daemon 決定並記 log（worker 結束即回收；worker 不自殺、不寫台帳）
    - process.py 台帳（append-only JSONL）：全部 spawn 一行一筆（pid／ppid／program／chat_id／message_id）
    ====================================================================
    """

    _instance: "daemon_manager_class | None" = None
    _daemon_conf_data: dict
    _daemon_conf_stat: tuple | None

    def __new__(cls):
        """單例：整個程序只建立一次（若未來動作需要累積狀態，集中在同一實例）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    def __init__(self):
        """實例化時注入默認 daemon_conf（僅首次生效——避免重複呼叫覆寫已載入的配置）。"""
        # 已有配置 → 跳過（__init__ 會在每次 cls() 時被呼叫——singleton 下不應重設）
        if hasattr(self, "_daemon_conf_data"):
            return

        self._daemon_conf_data = {}

        # runtime.json 上次讀取嘅指紋（mtime_ns＋size——防同刻度改寫漏更新）
        self._daemon_conf_stat = None

    @classmethod
    def get_instance(cls) -> "daemon_manager_class":
        """取得 daemon_manager_class 單例（整個程序共用同一實例）。"""
        return cls()

    def get_daemon_conf_data(self) -> dict:
        """取得目前 daemon_conf（load_config 已注入）。"""
        return self._daemon_conf_data

    def initialize(self) -> None:
        """開機：重複檢查（有更早嘅活 daemon → 自己退出）→ 載入 runtime.json。"""
        single_instance.exit_if_duplicate(PROGRAM_NAME)
        self.load_config()

        # 收場協議（經理自殺：先禮後兵——工人走清才自殺）
        lifecycle.set_shutdown_handler(job_exit.exit_manager)

        # 開機＝活躍（刷新標記，避免舊檔案 mtime 令第一拍就判閒置）
        presence.touch()

    def load_config(self) -> bool:
        """
        重新解析固定 runtime.json（data/runtime.json）→ 依靜態 SSOT 展開 → merge。

        無副作用（唔宣告 boot）——熱重載與 worker 共用；每次載入即刷新 .env 值同 API keys。
        回傳：True = 成功更新；False = 變更無（mtime 相同——正常，非錯誤）。
        失敗（讀不到 / JSON 壞）→ log + lifecycle.exit（連子程序退出）。
        """
        # .env 值（機器層——讀成 dict）
        sc_env.init_env()

        # API keys（*_API_KEY 寫入 os.environ——goose 子程序繼承）
        sc_env.load_api_keys()

        # 讀取 runtime.json（唯一來源；sc_config 快取）
        sc_config.init_config()

        loaded_conf = dict(sc_config.get_configs())

        # merge 後比對——config 沒變就跳過
        new_conf = {**self._daemon_conf_data, **loaded_conf}
        if new_conf == self._daemon_conf_data:
            return False

        # 更新配置
        self._daemon_conf_data = new_conf

        return True

    def reload_config(self) -> None:
        """
        每輪重讀 runtime.json → 變更即生效（主迴圈每拍呼叫）。

        機制：檔案指紋（mtime_ns＋size）快速檢測 → 未變更跳過 → 變更才呼叫 load_config 重載。
        """
        try:
            file_stat = os.stat(RUNTIME_CONFIG_FILE)
        except OSError:
            return

        current_fingerprint = (file_stat.st_mtime_ns, file_stat.st_size)

        # 指紋未變更 → 跳過（零開銷）
        if current_fingerprint == self._daemon_conf_stat:
            return

        # 指紋變更 → 先記低（即使內容一樣，都唔會每拍重試）
        self._daemon_conf_stat = current_fingerprint

        # 重載（load_config：無副作用）
        self.load_config()

    def run(self) -> int:
        """
        daemon 主迴圈（冷啟動已完成——initialize 負責）。

        流程（觸發 → 處理 → 輸出）：

            迴圈：重載配置 → 收訊號／回收／監督 → 調度 msg_conf（暖 worker，非阻塞）→ 閒置檢查 → 休眠
        """
        # 背景監看（runtime.json 消失／BUILD 變更 → 自殺；同 bridge 同一函數——先開監看）
        lifecycle.watch_runtime()

        # 心跳（背景執行緒——每秒寫狀態檔＋監視母親；確認 runtime 有效才開始跳）
        heartbeat.init()

        sc_logger.log(f"daemon started (model={self._daemon_conf_data.get('model_name', '')})")

        # PHP 只寫檔唔通知，daemon 冇通道，只能每秒掃。
        # 即：PHP 冇直接叫 daemon「有新訊息」，只係間接寫入目錄；
        # daemon 唯有每秒偵察 messages/polling，睇有冇新 prompt。
        # 主迴圈：重讀配置 → 收訊號／回收／監督 → 調度 pending → 閒置檢查 → 休眠
        while True:
            try:
                self._tick()
            # 盲捕例外（刻意——迴圈錯誤不終止 daemon）
            except Exception as exc:  # noqa: BLE001
                sc_logger.log(f"loop error: {exc}")

            # 固定節奏（秒——見 daemon_config.MAIN_LOOP_INTERVAL_SEC）
            time.sleep(daemon_config.MAIN_LOOP_INTERVAL_SEC)

    def _tick(self) -> None:
        """主迴圈一拍：刷新配置 → 知情（工單表）→ 派工 → 資源回收／監督 → 閒置檢查。"""
        # 第一步：刷新配置（每拍檢查 mtime；變更才重載）
        self.reload_config()

        # 第二步：知情——掃 polling 目錄，增量載入工單表
        job_scanner.load()

        # 第三步：派工——取未派工單 → 搵空閒 worker → 冇就開新
        job_scheduler.dispatch()

        # ── 以下三步都係處理工人，但對象唔同 ──
        # 第四步：收訊號——對「做完單」嘅工人（job_done → 回空閒）
        job_signal.collect_worker_signals()

        # 第五步：清屍——對「已經死咗」嘅工人（join 清 zombie＋收工單終態＋除名＋log）
        job_scheduler.reap_workers()

        # 第六步：巡邏——對「未死但卡住」嘅工人（取消／逾時 → 主動殺＋收終態）
        # 註：閒置 30 秒自殺係工人自己做，唔關呢步事
        watchdog.supervise_workers()

        # 有工人 → 刷新活躍標記；冇工人 → 閒置判斷（夠鐘即連子程序退出）
        if len(roster.get_worker_entries()) > 0:
            presence.touch()
        else:
            presence.exit_when_idle()

    def _format_timestamp(self, timestamp: int) -> str:
        return time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(timestamp))

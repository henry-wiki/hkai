#!/usr/bin/env python3
"""
daemon_manager.py

daemon_manager_class 單例——所有「動作(action)」集中於此（生命週期調度）。

主迴圈（main.py 只負責一行呼叫，不重複寫判斷邏輯——單一事實來源）：

    load_config()               重新解析 runtime.json（無副作用）
    run()                       主迴圈（runtime 監看 → 心跳 → 每拍 tick → 休眠）

未來若要新增動作（例如：強制重啟、清快取、重載 daemon_conf……）一律加在這裡。
"""

import os
import time
import traceback
from pathlib import Path

from ..context import IDLE_EXIT_SEC, PROGRAM_NAME, RUNTIME_CONFIG_FILE
from bootstrap import config, env
from bootstrap.load import load
from bootstrap import sc_logger
from ..runtime import (
    heartbeat,
    lifecycle,
    single_instance,
)
from . import (
    daemon_config,
    job_exit,
    job_scanner,
    job_scheduler,
    job_signal,
    roster,
    watchdog,
)


# 閒置時鐘（最後一次有工人嘅時間——記憶體；重啟由開機重新計）
_last_worker_time = time.time()


def _reset_idle_timer() -> None:
    """開機對時（daemon 啟動＝閒置時鐘起點）。"""
    global _last_worker_time

    _last_worker_time = time.time()


def _touch_idle_timer() -> None:
    """刷新閒置時鐘（名冊有工人——每拍一次）。"""
    global _last_worker_time

    _last_worker_time = time.time()


def _idle_age_sec() -> float:
    """閒置秒數（最後有工人／開機至今）。"""
    return time.time() - _last_worker_time


def _exit_when_idle() -> None:
    """閒置 ≥ IDLE_EXIT_SEC → 連子孫退出。"""
    idle_sec = _idle_age_sec()

    # 判定：未夠門檻 → 繼續
    if idle_sec < IDLE_EXIT_SEC:
        return

    sc_logger.log(f"idle {idle_sec:.0f}s, exit with children")
    lifecycle.exit("idle timeout", 0)




def _code_stamp() -> float:
    """回傳程式碼最新 mtime（goose_daemon/ 內全部 *.py）。"""
    package_dir = Path(__file__).resolve().parents[1]
    latest_mtime = 0.0

    for path_item in package_dir.rglob("*.py"):
        latest_mtime = max(latest_mtime, path_item.stat().st_mtime)

    return latest_mtime


class daemon_manager_class:
    """daemon_manager_class 單例（整個程序只有一個實例——get_instance() 取得）。

    生命週期（現行）：
    - 由 PHP 冷啟動（popen）——與網頁／瀏覽器無生命週期連結，長駐背景
    - 開機一次：重複實例檢查 → 載入 runtime.json（.env／API key）→ 註冊經理協議 → _reset_idle_timer()
    - 每拍（MAIN_LOOP_INTERVAL_SEC）：刷新配置 → 掃 polling 增量 → 派工（空閒 worker／開新）
      → 收訊號 → 收屍 → 巡邏 → 閒置時鐘（有工人刷新；冇工人且夠 IDLE_EXIT_SEC → 連子程序退出）
    - 退出路徑：runtime.json 消失／BUILD 變（lifecycle.watch_runtime）／閒置（_exit_when_idle）
      ／經理協議（job_exit.exit_manager——工人走清才自殺）
    - 心跳：heartbeat 背景執行緒每秒寫狀態檔；舊「PHP 更新心跳 mtime、daemon 超時自殺」機制已移除
    ----------------------------------------------------------------------
    """

    _instance: "daemon_manager_class | None" = None
    _daemon_conf_data: dict[str, object]
    _daemon_conf_stat: tuple[int, int] | None

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

    def get_daemon_conf_data(self) -> dict[str, object]:
        """取得目前 daemon_conf（load_config 已注入）。"""
        return self._daemon_conf_data

    def initialize(self) -> None:
        """開機：重複檢查（有更早嘅活 daemon → 自己退出）→ 載入 runtime.json。"""
        _ = single_instance.exit_if_duplicate(PROGRAM_NAME)
        _ = self.load_config()

        # 收場協議（經理自殺：先禮後兵——工人走清才自殺）
        lifecycle.set_shutdown_handler(job_exit.exit_manager)

        # 開機＝閒置時鐘起點（記憶體計時，重啟由 0 重新計）
        _reset_idle_timer()

        # 記錄開機時代碼版本（主迴圈比較——有變即退出交棒）
        self._code_stamp_at_start = _code_stamp()

    def load_config(self) -> bool:
        """
        重新解析固定 runtime.json（data/runtime.json）→ 依靜態 SSOT 展開 → merge。

        無副作用（唔宣告 boot）——熱重載與 worker 共用；每次載入即刷新 .env 值同 API keys。
        回傳：True = 成功更新；False = 變更無（mtime 相同——正常，非錯誤）。
        失敗（讀不到 / JSON 壞）→ log + lifecycle.exit（連子程序退出）。
        """
        # 運行前載入（.env → 資料根 → runtime.json）＋ API keys（*_API_KEY 寫入 os.environ——goose 子程序繼承）
        try:
            load()
            env.load_api_keys()
        # 判定：載入失敗（.env／runtime.json 壞）→ 記錄 + 連子程序退出（唔可以帶空設定繼續跑）
        except Exception:  # noqa: BLE001
            sc_logger.error("load_config failed — daemon exiting\n" + traceback.format_exc())
            lifecycle.exit("load_config failed", 1)

        loaded_conf = dict(config.get_configs())

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
        _ = self.load_config()

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
        # 第零步：程式碼有變 → 自己退出（下次工單由 bridge 用新碼重開）
        current_code_stamp = _code_stamp()

        # 判定：程式碼比開機時新 → 退出交棒
        if current_code_stamp > self._code_stamp_at_start:
            sc_logger.log("code changed (auto reload) — daemon exiting")
            lifecycle.exit("code changed (auto reload)", 0)

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

        # 有工人 → 刷新閒置時鐘；冇工人 → 閒置判斷（夠鐘即連子程序退出）
        if len(roster.get_worker_entries()) > 0:
            _touch_idle_timer()
        else:
            _exit_when_idle()

    def _format_timestamp(self, timestamp: int) -> str:
        return time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(timestamp))

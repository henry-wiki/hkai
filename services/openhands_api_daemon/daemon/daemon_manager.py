#!/usr/bin/env python3
"""
daemon_manager.py

daemon_manager_class 單例——所有「動作(action)」集中於此（生命週期調度）。

主迴圈（main.py 只負責一行呼叫，不重複寫判斷邏輯——單一事實來源）：

    load_config()               重新解析 runtime.json（無副作用）
    run()                       主迴圈（單一實例驗證 → 心跳自殺 → 重載配置 → 掃描處理 → 休眠）

未來若要新增動作（例如：強制重啟、清快取、重載 daemon_conf……）一律加在這裡。
"""

import json
import multiprocessing
import os
import queue
import time
from pathlib import Path

from ..agent.auth_setup import api_key_loader_class
from ..context import *
from ..runtime import process_list
from ..runtime import lifecycle
from ..runtime import sc_config
from ..runtime import process_status
import lifecycle_guard
from ..runtime import sc_logger


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
    - 由 daemon 統一調度 worker 子程序（同時最多 daemon_max_workers 個；同對話一次一條）
    - 殺／留集中由 daemon 決定並記 log（worker 結束即回收；worker 不自殺、不寫台帳）
    - process_list（append-only JSONL）：全部 spawn 一行一筆（name／pid／ppid／program／chat_id／message_id）
    ====================================================================
    """

    _instance: "daemon_manager_class | None" = None
    _daemon_conf_data: dict
    _daemon_conf_data_path: Path
    _daemon_conf_data_mtime: float

    def __new__(cls):
        """單例：整個程序只建立一次（若未來動作需要累積狀態，集中在同一實例）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    def __init__(self):
        """實例化時注入默認 daemon_conf（僅首次生效——避免重複呼叫覆寫已載入的配置）。"""
        # 已有配置 → 跳過（__init__ 會在每次 cls() 時被呼叫——singleton 下不應重設）
        try:
            if self._daemon_conf_data:
                return
        except AttributeError:
            pass

        self._daemon_conf_data = {
            "daemon_heartbeat_check_interval": 1.0,
            "daemon_idle_timeout_sec": 900,
            "daemon_max_workers": 3,
            "daemon_child_memory_limit_mb": 3072,
            "daemon_warm_workers": False,
            "daemon_warm_prewarm_workers": 1,
            "daemon_warm_idle_recycle_sec": 3600,
        }

        # 活躍 worker（pid → worker 記錄＋Process 物件——由 daemon 主程序統一管理）
        self._active_workers: dict[int, dict] = {}

        # 最後有 worker 活動嘅時間（閒置自殺計時）
        self._last_active_at = time.time()

    @classmethod
    def get_instance(cls) -> "daemon_manager_class":
        """取得 daemon_manager_class 單例（整個程序共用同一實例）。"""
        return cls()

    def get_daemon_conf_data(self) -> dict:
        """取得目前 daemon_conf（load_config 已注入）。"""
        return self._daemon_conf_data

    def get_daemon_conf_path(self) -> Path:
        """取得 runtime.json 路徑（load_config 已注入）。"""
        return self._daemon_conf_data_path

    def initialize(self) -> None:
        """載入固定 runtime.json（由 main.py 呼叫）。"""
        self.load_config()

    def load_config(self) -> bool:
        """
        重新解析固定 runtime.json（data/runtime.json）→ 依靜態 SSOT 展開 → merge。

        無副作用（唔宣告 boot、唔載 keys）——熱重載與 worker 共用。
        回傳：True = 成功更新；False = 變更無（mtime 相同——正常，非錯誤）。
        失敗（讀不到 / JSON 壞）→ log + lifecycle.exit（連子程序退出）。
        """
        self._daemon_conf_data_path = RUNTIME_CONFIG_FILE

        # 讀取 runtime.json（唯一來源；sc_config 快取）
        sc_config.init_config()
        loaded_conf = dict(sc_config.get_configs())

        # 依靜態 SSOT 展開 runtime spec（動態先、靜態後）
        loaded_conf = self._apply_static_configs(loaded_conf)

        # merge 後比對——config 沒變就跳過
        new_conf = {**self._daemon_conf_data, **loaded_conf}
        if new_conf == self._daemon_conf_data:
            return False

        # 更新配置
        self._daemon_conf_data = new_conf
        self._daemon_conf_data_mtime = os.path.getmtime(RUNTIME_CONFIG_FILE)

        return True

    def _load_api_keys(self) -> None:
        """載入 API keys（只限冷啟動——worker 經環境變數繼承）。"""
        try:
            api_key_loader_class.get_instance().load(ENV_FILE)
        except Exception as exc:  # noqa: BLE001
            sc_logger.log(f"cannot load oauth keys: {exc}")
            lifecycle.exit("cannot load oauth keys", 1)

    # 依 runtime.json 自帶嘅 agent 組展開設定（路徑絕對值由 PHP 注入）
    def _apply_static_configs(self, runtime_spec: dict) -> dict:
        openhands_api_daemon   = runtime_spec["openhands_api_daemon"]

        # daemon 行為設定（靜態 SSOT——openhands_api_daemon group）
        runtime_spec["daemon_heartbeat_check_interval"] = openhands_api_daemon["heartbeat_check_interval"]
        runtime_spec["daemon_idle_timeout_sec"]         = openhands_api_daemon["idle_timeout_sec"]
        runtime_spec["daemon_max_workers"]              = openhands_api_daemon.get("max_workers", 3)
        runtime_spec["daemon_child_memory_limit_mb"]    = int(openhands_api_daemon.get("child_memory_limit_mb", 3072))
        # 暖池設定（靜態 SSOT——暖 worker 常駐；false＝行回冷路徑）
        runtime_spec["daemon_warm_workers"]             = bool(openhands_api_daemon.get("warm_workers", False))
        runtime_spec["daemon_warm_prewarm_workers"]     = int(openhands_api_daemon.get("warm_prewarm_workers", 1))
        runtime_spec["daemon_warm_idle_recycle_sec"]    = int(openhands_api_daemon.get("warm_idle_recycle_sec", 3600))

        # 傳輸模式（acp＝協定互動路徑／cli＝stream-json——openhands_api_entry 判定用）
        runtime_spec["daemon_transport"] = str(openhands_api_daemon.get("transport", "acp"))

        # chat_mode 對映（本組 UI 模條目——抽 mode 欄入表；缺＝空表＝agent自身默認）
        chat_mode_config = openhands_api_daemon.get("chat_mode", {})
        chat_mode_map = {}
        # 逐個 UI 模條目抽原生模式（value 有 mode 欄＝UI 模）
        for chat_mode_name, chat_mode_item in chat_mode_config.items():
            # 判定：非 UI 模條目（唔係 dict）→ 跳
            if not isinstance(chat_mode_item, dict):
                continue
            # 判定：無 mode 欄 → 跳
            if "mode" not in chat_mode_item:
                continue
            chat_mode_map[chat_mode_name] = str(chat_mode_item["mode"])
        runtime_spec["daemon_chat_mode_map"] = chat_mode_map

        # 外部路徑（靜態 SSOT）
        return runtime_spec

    def run(self) -> int:
        """
        daemon 主迴圈（冷啟動已完成——initialize 負責）。

        流程（觸發 → 處理 → 輸出）：

            迴圈：單一實例驗證 → 重載配置 → 回收 worker → 調度 msg_conf（非阻塞）→ 休眠
        """
        # 冷啟動：boot 宣告（台帳＋心跳）＋載入 API keys
        self._announce_daemon_boot()
        self._load_api_keys()

        sc_logger.log(f"daemon started (model={self._daemon_conf_data.get('model_name', '')})")

        # 判定：暖池模式 → 開機預熱（背景拉agent——不阻塞主迴圈）
        if self._daemon_conf_data.get("daemon_warm_workers", False):
            self._prewarm_warm_workers()

        # BUILD 監看（開機基準值——變咗即自我退出，等新版本再拉起）
        self._build_watch = lifecycle_guard.build_watch_class(lifecycle_guard.find_site_root(self._daemon_conf_data_path))

        # 主迴圈：單一實例驗證 → 重讀配置 → 收訊號／回收／監督 → 調度 pending → 休眠
        while True:
            try:
                # 母親（bridge）唔在 → 自殺（NEED_MOTHER=True）
                lifecycle.exit_when_mother_gone()

                # 單一實例驗證（防多 daemon 並存——詳見 _verify_single_instance）
                self._verify_single_instance()

                # 判定：daemon 設定檔已被刪（建置／清理）→ 連子程序退出，不留孤兒
                if self._daemon_conf_data_path.is_file() is False:
                    sc_logger.log("daemon conf removed, exit with children")
                    process_status.unregister("openhands_api_daemon", os.getpid())
                    lifecycle.exit("self exit", 0)

                # 判定：BUILD 已變（站台已重新發佈）→ 記錄後連子程序退出，等新版本再拉起
                build_watch_result = self._build_watch.check()
                if build_watch_result["changed"] is True:
                    sc_logger.log("BUILD changed " + build_watch_result["previous"] + " -> " + build_watch_result["current"] + ", exit with children")
                    process_status.unregister("openhands_api_daemon", os.getpid())
                    lifecycle.exit("self exit", 0)

                # 每輪重讀配置（PHP 動態控制——改檔即生效）
                self._reload_daemon_conf()

                # 暖池維護：收 worker 訊號（ready／job_done）
                self._collect_warm_worker_signals()

                # 回收已結束 worker（join＋記 log；運行秒數即 elapsed）
                self._reap_workers()

                # 暖池維護：監督工單（取消／逾時 → 殺＋收終態）
                self._supervise_busy_warm_workers()

                # 暖池維護：回收閒置暖 worker（釋放記憶體）
                self._recycle_idle_warm_workers()

                # 掃描 msg_conf → 調度 worker（非阻塞——同對話一次一條；同時最多 max_workers）
                self._process_pending_asks()

                # 程序狀態（自我心跳——每輪寫一次：本程序存在＝在跑）
                process_status.beat_all()

                # 閒置自殺（冇 worker 超過 daemon_idle_timeout_sec → 連子程序退出）
                self._exit_when_idle()

            # 盲捕例外（刻意——迴圈錯誤不終止 daemon）
            except Exception as exc:  # noqa: BLE001
                sc_logger.log(f"loop error: {exc}")

            # 迴圈間隔 = 心跳檢查間隔（PHP 控制——heartbeat_check_interval）
            time.sleep(self._daemon_conf_data["daemon_heartbeat_check_interval"])

    def _exit_when_idle(self) -> None:
        """閒置（冇 worker 在跑）超過 daemon_idle_timeout_sec → 連子程序自殺。"""
        # 判定：有 worker 在跑 → 刷新活躍時間
        if len(self._active_workers) > 0:
            self._last_active_at = time.time()
            return

        idle_timeout_sec = self._daemon_conf_data["daemon_idle_timeout_sec"]
        idle_sec = time.time() - self._last_active_at

        # 判定：未夠閒置上限 → 繼續
        if idle_sec < idle_timeout_sec:
            return

        sc_logger.log(f"idle {idle_sec:.0f}s, exit with children")
        lifecycle.exit("idle timeout", 0)

    def _verify_single_instance(self) -> None:
        """
        單一實例驗證 → process_list 最後一筆 openhands_api_daemon 記錄嘅 pid 唔係自己就自殺退出（主迴圈每輪一行呼叫）。

        背景：daemon 由 PHP 拉起；process_list（append-only）——boot 時最後 append 嘅 daemon 為勝方。
        機制：讀 process_list 最後一筆 name=openhands_api_daemon 記錄嘅 pid，比對自己 os.getpid()。
        - 檔不存在／無記錄 → 忽略（下次再驗證）
        - pid 唔同 → 另一實例（後寫者為勝）→ 自己退出
        - pid 相同 → 唯一實例 → 繼續
        """
        # 台帳路徑（runtime spec——未展開／未啟用 → 忽略）
        process_list_path = process_list.get_list_path(self._daemon_conf_data)
        if process_list_path is None:
            return

        pid_on_list = 0

        try:
            with open(process_list_path, "r", encoding="utf-8") as process_list_file:
                # 由尾向前掃——最先遇到嘅 openhands_api_daemon 記錄即最新一筆
                for process_list_line in reversed(process_list_file.readlines()):
                    process_list_line = process_list_line.strip()

                    # 判定：空行 → 跳過
                    if process_list_line == "":
                        continue

                    process_record = json.loads(process_list_line)

                    # 判定：非本程序記錄 → 跳過
                    if (process_record.get("program") or process_record.get("name", "")) != "openhands_api_daemon":
                        continue

                    pid_on_list = int(process_record.get("pid", 0))
                    break
        except Exception:  # noqa: BLE001
            return

        # pid 為 0（無記錄）→ 忽略（下次再驗證）
        if pid_on_list == 0:
            return

        # pid 與自己不同 → 另一實例（後寫者為勝）→ 自殺
        if pid_on_list != os.getpid():
            sc_logger.log("process_list last openhands_api_daemon mismatch, another daemon instance, self kill")
            lifecycle.exit("self exit", 0)


    def _reload_daemon_conf(self) -> None:
        """
        每輪重讀 runtime.json → 變更即生效（主迴圈每輪一行呼叫）。

        機制：mtime 快速檢測 → 未變更跳過 → 變更才呼叫 load_config 重載。
        """
        try:
            current_mtime = os.path.getmtime(self._daemon_conf_data_path)
        except OSError:
            return

        # mtime 未變更 → 跳過（零開銷）
        if current_mtime == self._daemon_conf_data_mtime:
            return

        # mtime 變更 → 重載（load_config：無副作用）
        self.load_config()


    def _process_pending_asks(self) -> None:
        """
        掃描 messages/polling/*/*/msg_conf.json → 調度 worker（**非阻塞**——本方法立即返回）。

        規則：
        - 同時最多 daemon_max_workers 個 worker（預設 3——由 PHP 靜態設定控制）
        - 同一對話同時只跑一條（該 chat 已有 worker → 跳過，留待下輪）
        - 最舊優先（get_msg_conf_files 已按建立時間排序）
        - 已終態／已有 response 的 msg_conf → 跳過（等 PHP 歸檔——避免重複 spawn）
        """
        msg_conf_paths = msg_main().get_msg_conf_files()

        # 無待處理查詢 → 記錄並返回
        if not msg_conf_paths:
            sc_logger.log("no pending asks")
            return

        # 判定：暖池模式 → 派工單制（規則同冷路徑——見 _process_pending_asks_warm）
        if self._daemon_conf_data.get("daemon_warm_workers", False):
            self._process_pending_asks_warm(msg_conf_paths)
            return

        max_workers = int(self._daemon_conf_data.get("daemon_max_workers", 3))

        # 逐筆調度（最舊優先；滿載或同對話已跑 → 跳過）
        for msg_conf_path in msg_conf_paths:
            # 判定：worker 已滿 → 停止本輪調度（其餘留待下輪）
            if len(self._active_workers) >= max_workers:
                break

            # 讀取身分＋完成判斷（不可用／已完成 → None）
            worker_identity = self._read_worker_identity(msg_conf_path)

            # 判定：不可用或已完成 → 跳過
            if worker_identity is None:
                continue

            # 判定：該對話已有 worker 在跑 → 跳過（同對話一次一條）
            if self._has_active_chat_worker(worker_identity["chat_id"]):
                continue

            self._spawn_worker(msg_conf_path, worker_identity)

    # 讀取 msg_conf 身分（chat_id／message_id）；不可解析、唔係本agent、已終態、已有活主人 → None
    def _read_worker_identity(self, msg_conf_path: Path) -> dict | None:
        try:
            conf_data = json.loads(msg_conf_path.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return None

        # 判定：非 dict → 不可用
        if not isinstance(conf_data, dict):
            return None

        # 判定：訊息蓋章唔係本agent → 唔接（agent 分流——兩 daemon 並存各自接自己嗰批）
        target_daemon_engine = str(conf_data.get("daemon_engine", ""))

        # 判定：缺章（舊訊息）→ 現行agent接手（runtime 動態值）
        if target_daemon_engine == "":
            target_daemon_engine = str(self._daemon_conf_data.get("daemon_engine", ""))

        if target_daemon_engine != "openhands_api_daemon":
            return None

        chat_id    = str(conf_data.get("chat_id", ""))
        message_id = str(conf_data.get("message_id", ""))

        # 判定：缺識別 → 不可用
        if chat_id == "" or message_id == "":
            return None

        # 完成判斷（身分已確定——載入 msg_main 供 status／response 查詢）
        msg_main().load_msg_conf_data(msg_conf_path)

        # 判定：已終態 → 不需 spawn（PHP 尚未歸檔）
        current_status = msg_main().get_msg_status()
        if msg_state_machine().is_terminal_status(current_status):
            return None

        # 判定：已有活主人（認領）→ 不需 spawn
        if msg_claim().is_owner_alive():
            return None

        # 逾時預算（msg_conf 隨身——daemon 監督 busy worker 用）
        connection_timeout = int(conf_data.get("connection_timeout", 600))
        startup_timeout    = int(conf_data.get("startup_timeout", 180))

        return {
            "chat_id": chat_id,
            "message_id": message_id,
            "connection_timeout": connection_timeout,
            "startup_timeout": startup_timeout,
        }

    # 該對話是否已有 worker 在跑（同對話一次一條）
    def _has_active_chat_worker(self, chat_id: str) -> bool:
        for worker_entry in self._active_workers.values():
            # 判定：同 chat_id → 已在跑
            if worker_entry["chat_id"] == chat_id:
                return True
        return False

    # spawn worker 子程序（非阻塞——立即返回）；台帳 append＋記 log
    def _spawn_worker(self, msg_conf_path: Path, worker_identity: dict) -> None:
        from .worker import run_worker

        daemon_conf_path = str(self._daemon_conf_data_path)
        # 注意：worker 不可設 daemon=True——worker 內還要再 spawn agent子程序
        #       （Python 限制：daemonic process 不能再有 children）
        worker_proc = multiprocessing.Process(
            target=run_worker,
            args=(daemon_conf_path, str(msg_conf_path)),
            daemon=False,
        )
        worker_proc.start()

        # 判定：spawn 失敗（無 pid）→ 記 log 返回
        if worker_proc.pid is None:
            sc_logger.log(f"worker spawn failed: {msg_conf_path.name}")
            return

        started_at = int(time.time())
        worker_info = {
            "chat_id": worker_identity["chat_id"],
            "message_id": worker_identity["message_id"],
            "program": "openhands_api_daemon.worker",
            "source": "chat:" + worker_identity["chat_id"],
            "started_at": started_at,
        }
        self._active_workers[worker_proc.pid] = {**worker_info, "proc": worker_proc}


        # 台帳（append-only JSONL）：worker 子程序——daemon 為 spawn 者
        process_list.append_spawn(
            self._daemon_conf_data,
            program="worker",
            program_label=worker_info["program"],
            pid=worker_proc.pid,
            chat_id=worker_identity["chat_id"],
            message_id=worker_identity["message_id"],
        )

        sc_logger.log(
            "worker spawned pid=" + str(worker_proc.pid)
            + " chat=" + worker_identity["chat_id"]
            + " message=" + worker_identity["message_id"])

    def _reap_workers(self) -> None:
        """
        回收已結束 worker：join → 記 log（含運行秒數、exitcode）。

        存活 worker → 繼續（運行秒數即時計——唔使更新任何檔）。
        """
        # 逐 worker 檢查（copy keys——回收時會變更 dict）
        for worker_pid in list(self._active_workers.keys()):
            worker_entry = self._active_workers[worker_pid]
            worker_proc  = worker_entry["proc"]

            # 判定：仍在跑 → 更新運行秒數，繼續
            if worker_proc.is_alive():
                continue

            worker_proc.join()
            elapsed_sec = int(time.time() - worker_entry["started_at"])

            # 判定：暖 worker（有訊息通道欄）→ 死亡清尾（收終態＋登出）
            if worker_entry.get("status_queue") is not None:
                self._cleanup_dead_warm_worker(worker_entry)

            del self._active_workers[worker_pid]

            sc_logger.log(
                "worker exited pid=" + str(worker_pid)
                + " chat=" + worker_entry["chat_id"]
                + " message=" + worker_entry["message_id"]
                + " elapsed=" + str(elapsed_sec) + "s"
                + " exitcode=" + str(worker_proc.exitcode))

    # ===== 暖池（warm pool）：常駐 worker 的派工／監督／回收 =====

    def _process_pending_asks_warm(self, msg_conf_paths: list) -> None:
        """暖池派工：msg_conf → 空閒暖 worker（非阻塞；規則同冷路徑——同對話一次一條）。"""
        max_workers = int(self._daemon_conf_data.get("daemon_max_workers", 3))

        # 逐筆派工（最舊優先；滿載或同對話已跑 → 跳過）
        for msg_conf_path in msg_conf_paths:
            # 讀取身分＋完成判斷（不可用／已完成 → None）
            worker_identity = self._read_worker_identity(msg_conf_path)

            # 判定：不可用或已完成 → 跳過
            if worker_identity is None:
                continue

            # 判定：該對話已有工單在跑 → 跳過（同對話一次一條）
            if self._has_active_chat_worker(worker_identity["chat_id"]):
                continue

            # 空閒暖 worker（已就緒＋無工單）——優先派工
            worker_entry = self._find_idle_warm_worker()

            # 判定：無空閒 worker → 未滿載就補開（邊熱邊排隊）；滿載就停本輪
            if worker_entry is None:
                # 判定：已達 max_workers 上限 → 停本輪（其餘留待下輪）
                if len(self._active_workers) >= max_workers:
                    break
                worker_entry = self._spawn_warm_worker()
                # 判定：spawn 失敗 → 停本輪（下輪再試）
                if worker_entry is None:
                    break

            self._dispatch_warm_job(worker_entry, msg_conf_path, worker_identity)

    # 開機預熱（背景拉agent——未就緒前的訊息排隊等，行為同冷啟動）
    def _prewarm_warm_workers(self) -> None:
        """預熱 warm_prewarm_workers 條暖 worker（daemon 開機一次）。"""
        prewarm_count = int(self._daemon_conf_data.get("daemon_warm_prewarm_workers", 1))

        # 逐條拉起（spawn 非阻塞——立即返回）
        for _warm_index in range(prewarm_count):
            self._spawn_warm_worker()

    # spawn 一條暖 worker（一次性載入agent——往後工單零開機成本）
    def _spawn_warm_worker(self):
        """建立暖 worker（job_queue／status_queue 隨 spawn 傳入）→ 回傳登錄項（失敗＝None）。"""
        from .worker import run_warm_worker

        daemon_conf_path = str(self._daemon_conf_data_path)
        job_queue = multiprocessing.Queue()
        status_queue = multiprocessing.Queue()
        # 注意：worker 不可設 daemon=True——保持與冷路徑一致（子程序可再有 children）
        worker_proc = multiprocessing.Process(
            target=run_warm_worker,
            args=(daemon_conf_path, job_queue, status_queue),
            daemon=False,
        )
        worker_proc.start()

        # 判定：spawn 失敗（無 pid）→ 記 log 返回（呼叫端容錯）
        if worker_proc.pid is None:
            sc_logger.log("warm worker spawn failed")
            return None

        worker_entry = {
            "chat_id": "",
            "message_id": "",
            "program": "openhands_api_daemon.warm_worker",
            "source": "",
            "started_at": int(time.time()),
            "proc": worker_proc,
            "job_queue": job_queue,
            "status_queue": status_queue,
            "warm_ready": False,
            "msg_conf_path": "",
            "dispatched_at": 0,
            "job_connection_timeout": 0,
            "job_startup_timeout": 0,
            "idle_since": int(time.time()),
        }
        self._active_workers[worker_proc.pid] = worker_entry

        # 台帳（append-only JSONL）：暖 worker——daemon 為 spawn 者
        process_list.append_spawn(
            self._daemon_conf_data,
            program="worker",
            program_label=worker_entry["program"],
            pid=worker_proc.pid,
        )
        sc_logger.log("warm worker spawned pid=" + str(worker_proc.pid))
        return worker_entry

    # 空閒暖 worker（已就緒＋無工單——一條最多一張單）
    def _find_idle_warm_worker(self):
        """掃描登錄表——回傳第一個空閒暖 worker（無＝None）。"""
        for worker_entry in self._active_workers.values():
            # 判定：未就緒（仍預載中）→ 非空閒
            if not worker_entry.get("warm_ready"):
                continue
            # 判定：已有工單在身 → 非空閒
            if worker_entry.get("chat_id", "") != "":
                continue
            return worker_entry
        return None

    # 派工單（非阻塞——put 入 job_queue；worker 收到即執行）
    def _dispatch_warm_job(self, worker_entry: dict, msg_conf_path: Path, worker_identity: dict) -> None:
        """工單入隊＋登記當前對話（busy 標記——同對話一次一條據此判定）。"""
        worker_entry["chat_id"] = worker_identity["chat_id"]
        worker_entry["message_id"] = worker_identity["message_id"]
        worker_entry["msg_conf_path"] = str(msg_conf_path)
        worker_entry["dispatched_at"] = int(time.time())
        worker_entry["job_connection_timeout"] = int(worker_identity["connection_timeout"])
        worker_entry["job_startup_timeout"] = int(worker_identity["startup_timeout"])
        worker_entry["job_queue"].put(str(msg_conf_path))
        sc_logger.log(
            "job dispatched pid=" + str(worker_entry["proc"].pid)
            + " chat=" + worker_identity["chat_id"]
            + " message=" + worker_identity["message_id"])

    # 收集暖 worker 訊號（ready／job_done——每輪 drain；不阻塞）
    def _collect_warm_worker_signals(self) -> None:
        """逐 worker drain status_queue → 更新登錄狀態（非暖模式＝空操作）。"""
        # 判定：非暖模式 → 略過
        if not self._daemon_conf_data.get("daemon_warm_workers", False):
            return

        # 逐 worker 收訊號（copy keys——處理中可能變更 dict）
        for worker_pid in list(self._active_workers.keys()):
            worker_entry = self._active_workers[worker_pid]
            status_queue = worker_entry.get("status_queue")
            # 判定：無訊號通道（冷 worker——保險）→ 跳過
            if status_queue is None:
                continue
            # 迴圈收盡本輪訊號（Empty＝收完）
            while True:
                try:
                    worker_signal = status_queue.get_nowait()
                except queue.Empty:
                    break
                self._apply_worker_signal(worker_entry, worker_signal)

    # 處理單一 worker 訊號（ready → 標就緒；job_done → 釋放工單位）
    def _apply_worker_signal(self, worker_entry: dict, worker_signal: dict) -> None:
        """依訊號事件更新 worker 登錄（ready／job_done 兩種）。"""
        signal_event = str(worker_signal.get("event", ""))

        # 判定：agent預載完成 → 標記就緒（可派工單）
        if signal_event == "ready":
            worker_entry["warm_ready"] = True
            worker_entry["idle_since"] = int(time.time())
            sc_logger.log("warm worker ready pid=" + str(worker_entry["proc"].pid))
            return

        # 判定：工單完成 → 清空當前對話（回空閒）
        if signal_event == "job_done":
            worker_entry["chat_id"] = ""
            worker_entry["message_id"] = ""
            worker_entry["msg_conf_path"] = ""
            worker_entry["dispatched_at"] = 0
            worker_entry["idle_since"] = int(time.time())
            return

    # 監督繁忙暖 worker（取消／逾時 → 殺＋收終態；補開由派工輪自動處理）
    def _supervise_busy_warm_workers(self) -> None:
        """逐忙碌暖 worker 偵測異常——異常即停（worker 阻塞中無法自理）。"""
        # 判定：非暖模式 → 略過
        if not self._daemon_conf_data.get("daemon_warm_workers", False):
            return

        # 逐 worker 檢查（copy keys——處理中會變更 dict）
        for worker_pid in list(self._active_workers.keys()):
            worker_entry = self._active_workers[worker_pid]
            # 判定：無工單在身（空閒）→ 略過
            if worker_entry.get("chat_id", "") == "":
                continue
            # 判定：程序已死 → 交由回收處理
            if not worker_entry["proc"].is_alive():
                continue
            kill_reason = self._detect_worker_stall(worker_entry)
            # 判定：無異常 → 繼續工作
            if kill_reason == "":
                continue
            sc_logger.log("warm worker killed pid=" + str(worker_pid) + " reason=" + kill_reason)
            self._stop_warm_worker(worker_entry, kill_reason)

    # 偵測工單異常（取消／逾時——回原因字串；正常＝空字串）
    def _detect_worker_stall(self, worker_entry: dict) -> str:
        """依訊息現狀＋經過秒數判定是否異常（queued／connecting 有寬限；其餘不干預）。"""
        msg_main().load_msg_conf_data(Path(worker_entry["msg_conf_path"]))
        current_status = msg_main().get_msg_status()
        elapsed_sec = int(time.time()) - int(worker_entry["dispatched_at"])

        # 判定：使用者已取消 → 立即終止
        if current_status == "cancelled":
            return "cancelled"

        # 判定：未被取單（queued）→ 寬限＝啟動＋連線逾時（含暖身等待）
        if current_status == "queued":
            if elapsed_sec > int(worker_entry["job_startup_timeout"]) + int(worker_entry["job_connection_timeout"]):
                return "queued timeout"
            return ""

        # 判定：已開跑未見首個回應（connecting）→ 寬限＝連線逾時
        if current_status == "connecting":
            if elapsed_sec > int(worker_entry["job_connection_timeout"]):
                return "connect timeout"
            return ""

        # 其餘（summarizing／replying／空）→ 工作中——不干預（agent自身重試接手）
        return ""

    # 回收閒置暖 worker（釋放記憶體——下次需要時重新預熱）
    def _recycle_idle_warm_workers(self) -> None:
        """閒置逾 warm_idle_recycle_sec 的暖 worker → 停（0＝不回收）。"""
        # 判定：非暖模式 → 略過
        if not self._daemon_conf_data.get("daemon_warm_workers", False):
            return
        recycle_sec = int(self._daemon_conf_data.get("daemon_warm_idle_recycle_sec", 3600))
        # 判定：設 0（不回收——長駐）→ 略過
        if recycle_sec <= 0:
            return

        # 逐 worker 檢查（copy keys——回收時會變更 dict）
        for worker_pid in list(self._active_workers.keys()):
            worker_entry = self._active_workers[worker_pid]
            # 判定：有工單在身 → 略過
            if worker_entry.get("chat_id", "") != "":
                continue
            idle_sec = int(time.time()) - int(worker_entry.get("idle_since", 0))
            # 判定：未達回收門檻 → 保留
            if idle_sec <= recycle_sec:
                continue
            sc_logger.log("warm worker idle recycle pid=" + str(worker_pid) + " idle=" + str(idle_sec) + "s")
            self._stop_warm_worker(worker_entry, "idle recycle")

    # 停止單一暖 worker（殺程序 → 死亡清尾 → 登出）
    def _stop_warm_worker(self, worker_entry: dict, reason: str) -> None:
        """安全終止暖 worker＋收尾（工單中死亡 → 該訊息收終態）。"""
        worker_proc = worker_entry["proc"]
        worker_pid = worker_proc.pid

        process_helper().safe_terminate(worker_proc)
        worker_proc.join()

        self._cleanup_dead_warm_worker(worker_entry)
        self._active_workers.pop(worker_pid, None)
        sc_logger.log("warm worker stopped pid=" + str(worker_pid) + " reason=" + reason)

    # 暖 worker 死亡清尾（工單中死亡 → 收終態；一律登出狀態檔）
    def _cleanup_dead_warm_worker(self, worker_entry: dict) -> None:
        """工單在身 → 幫該訊息收終態（防卡 connecting）；並登出 worker 狀態檔。"""
        # 判定：死於工單中 → 幫條訊息收終態
        if worker_entry.get("chat_id", "") != "":
            msg_processor().finalize_abandoned_message(Path(worker_entry["msg_conf_path"]))
        process_status.unregister("worker", worker_entry["proc"].pid)

    def _format_timestamp(self, timestamp: int) -> str:
        return time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(timestamp))

    def _announce_daemon_boot(self) -> None:
        """
        （內部）冷啟動宣告：process_list append 自己一筆（＋ppid）＋程序狀態初始化（自己登記＋清過期殘檔）。
        """
        # 台帳（append-only JSONL）：daemon 自身啟動——ppid 為拉起它的父程序
        process_list.append_spawn(
            self._daemon_conf_data,
            program="openhands_api_daemon",
            program_label="openhands_api_daemon.main",
            pid=os.getpid(),
            ppid=os.getppid(),
        )

        # 程序狀態（自我心跳）：初始化 ＋ 登記自己 ＋ 清掉過期殘檔（被強殺的程序不會刪自己的檔）
        process_status.initialize(self._daemon_conf_data["process_status_dir"])
        process_status.register("openhands_api_daemon", os.getpid(), "openhands_api_daemon.main")
        process_status.sweep_stale()


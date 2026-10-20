#!/usr/bin/env python3
"""
msg_processor.py

查詢四檔（msg_*）的流程處理（msg_processor_class singleton——get_instance() 取得）。

    messages/polling/{project_slug}/{message_id}/msg_conf.json   找到 → 處理（子程序寫 msg_stdout）
    → 完成/失敗 → status 寫入 msg_status（留 polling——PHP poll 收尾移 archive）

任何情況（成功/錯誤/例外）都要標記終態，避免卡檔；archive 目錄為純歸檔（PHP 收尾移入），本模組永不讀取。

連線狀態機（status 寫入 msg_status().txt 單行字串——poll 讀取；msg_conf 純設定永不修改；msg_log 記錄完整事件）：
    queued（建檔獲派工）→ connecting（子程序啟動＋等待首 chunk）→ summarizing（摘要串流）→ replying（主回答）→ completed / failed（留 polling 待 PHP 收尾）

執行模式（daemon_warm_workers——runtime 設定）：
    冷路徑（false）每訊息 spawn goose 子程序（逾時由本模組等待迴圈兜底）
    暖路徑（true）  暖 worker 同程序執行 run_job（取消／逾時由 daemon 監督兜底）
"""

import multiprocessing
from pathlib import Path

import os

from ..context import *
from ..msg.msg_events import *
from ..runtime import process_list
from ..runtime import process_status


class msg_processor_class:
    """
    查詢處理（singleton——整個程序只有一個實例）。

    職責單一：單一 msg_conf 的完整處理流程（單次 spawn＋連線逾時兜底＋收尾狀態）；檔案讀寫一律走 msg_main／msg_stdout／msg_claim 專責。
    """

    _instance: "msg_processor_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "msg_processor_class":
        """取得 msg_processor_class 單例（整個程序共用同一實例）。"""
        return cls()

    def process(self, msg_conf_path: Path) -> None:
        """
        處理單一 msg_conf 檔（主程序——子程序執行 run，主迴圈不阻塞）。

        流程：

            messages/polling/{project_slug}/{message_id}/msg_conf.json
            -> 單次 spawn 子程序（重試由 goose 引擎自理；連線逾時卡死兜底）
            -> 認領（原子——msg_claim）
            -> 連通：子程序首次輸出建立 msg_stdout
            -> 完成/失敗：四檔一併移入 archive/
        """

        # 載入 msg_conf（合併到 msg_main 狀態——供後續判斷用）
        msg_main().load_msg_conf_data(msg_conf_path)

        # 終態跳過（completed/failed/cancelled——有結束意思就停：PHP poll 負責歸檔，Python 不重處理）
        current_status = msg_main().get_msg_status()
        if msg_state_machine().is_terminal_status(current_status):
            return

        # stdout 已存在＝另一 daemon 做緊（防重）
        if msg_stdout().exists():
            return

        # msg_prompt（JSON——get_msg_prompt() 取 content 欄；使用者輸入原樣）
        content = msg_main().get_msg_prompt()

        # 沒有內容 → 直接標記完成（留 polling——PHP poll 收尾移 archive）
        if not content.strip():
            msg_state_machine().apply(EVENT_COMPLETED)
            return

        # 認領本訊息（原子——同時只一個）
        if msg_claim().claim(os.getpid()) is False:
            return

        # 收尾狀態（單次執行——spawn 子程序；連線逾時或子程序早亡即 fail）
        self._process_start(msg_conf_path)

    def _process_start(self, msg_conf_path: Path) -> None:
        """單次 spawn 執行查詢（重試全權交給 goose 引擎——同進程零 respawn）＋連線逾時兜底。"""
        # 判定：暖池模式 → 同程序執行（引擎已預載——無 spawn、無通道）
        if daemon().get_daemon_conf_data().get("daemon_warm_workers", False):
            self._process_start_warm(msg_conf_path)
            return

        # 逾時預算（皆由 conf 帶值——連線逾時＋子程序就緒逾時）
        msg_conf_data           = msg_main().get_msg_conf_data()
        connection_timeout      = int(msg_conf_data["connection_timeout"])
        startup_timeout_sec     = int(msg_conf_data["startup_timeout"])

        # 路徑（子程序需要——重初始化用）
        daemon_conf_path = str(daemon().get_daemon_conf_path())

        # 狀態轉換：spawn 前（→ queued）
        self._transition_status(EVENT_QUERY_DISPATCHED, "Spawning subprocess...")

        # 跨程序事件通道（FSM 內部——FSM 為唯一狀態管理者；子程序自行套用並回報）
        state_channel = msg_state_machine().create_channel()

        # spawn 子程序（唯一一次——重試由 goose 引擎自理）
        proc = multiprocessing.Process(
            target=goose_entry().run_coder,
            args=(daemon_conf_path, str(msg_conf_path), state_channel),
            daemon=True,
        )
        proc.start()  # 非阻塞啟動——子程序背景執行，主程序立刻繼續

        # 台帳（append-only JSONL）：goose 子程序——worker 為 spawn 者（本程序即父程序）
        chat_id    = str(msg_main().get_chat_id())
        message_id = str(msg_main().get_message_id())
        if proc.pid is not None:
            process_list.append_spawn(
                daemon().get_daemon_conf_data(),
                name="goose",
                program="goose_entry.run_coder",
                pid=proc.pid,
                chat_id=chat_id,
                message_id=message_id,
            )

        # 程序狀態（自我心跳）：goose 子程序由 worker 代寫（它是監護人——子程序自己不寫檔；心跳執行緒每秒覆寫）
        if proc.pid is not None:
            process_status.register("goose", proc.pid, "goose_entry.run_coder")

        # 註冊 child PID 到 registry（已停用——改由 daemon 統一以 worker PID 記錄；worker 不寫 registry）
        # chat_id = msg_main().get_chat_id()
        # if proc.pid is not None:
        #     daemon().add_child_pid(chat_id, proc.pid)

        # 子程序已 spawn（父程序視角——子程序已啟動，非「請求已送出」）→ 轉 connecting
        self._transition_status(EVENT_SUBPROCESS_SPAWNED, "Subprocess spawned")

        # 等 child 就緒（純觀察——不做收尾）
        subprocess_ready = self._wait_subprocess_ready(proc, startup_timeout_sec)

        # 判定：未就緒 → 終止子程序＋收尾（不進入觀察階段）
        if not subprocess_ready:
            process_helper().safe_terminate(proc)
            proc.join()
            self._finalize(proc)
            return

        # 觀察首個回應是否到達（純觀察——不做收尾）
        first_response_arrived = self._wait_first_response(proc, connection_timeout)

        # 判定：首個回應未到達 → 終止子程序
        if not first_response_arrived:
            process_helper().safe_terminate(proc)

        # 等子程序完全結束（回收——exitcode 在 join 後才有效）
        proc.join()

        # 收尾（依現時狀態決定終態）
        self._finalize(proc)

    def _process_start_warm(self, msg_conf_path: Path) -> None:
        """暖模式執行：同程序跑 job（引擎已預載）；取消／逾時由 daemon 監督裁決。"""
        # 狀態轉換：派工（→ queued）——事件與冷路徑一致（FSM 契約不變）
        self._transition_status(EVENT_QUERY_DISPATCHED, "Dispatching to warm worker...")

        # 子程序已就緒（暖＝永在）——同一事件照發（→ connecting）
        self._transition_status(EVENT_SUBPROCESS_SPAWNED, "Warm worker running")

        # 執行單次查詢（同程序——阻塞至完成；例外已由 run_job 內部記錄）
        goose_entry().run_job(str(msg_conf_path))

        # 收尾（依現時狀態決定終態——同冷路徑語義）
        self._finalize_warm()

    def _transition_status(self, event: str, message: str = "") -> str:
        """狀態轉換單一出口：經 FSM 轉移（唯一 msg_status 寫入者）＋記對應 level 的 msg_log。"""
        # 經 FSM（依 transitions 決定新狀態；未定義＝不改狀態）
        _next_state = msg_state_machine().apply(event)

        # 成功終態
        if _next_state == "completed":
            msg_log().append_success_log(event, message)
            msg_log().complete_all_poll_files()
        # 失敗終態
        elif _next_state == "failed":
            msg_log().append_error_log(event, message)
            msg_log().abort_all_poll_files()
        # 進行中（connecting／replying／summarizing…）
        else:
            msg_log().append_info_log(event, message)

        return _next_state

    def _wait_first_response(self, proc, connection_timeout: int) -> bool:
        """只觀察「首個回應是否到達」；True＝已到達（False＝逾時／早亡／取消）。"""
        first_response_arrived = False
        # 逾時旗標（預設逾時；非逾時結束——早亡／取消／首個回應到達——時改 False）
        timed_out = True

        # 逐秒等待（直到首個回應／早亡／取消／逾時）
        for sec in range(connection_timeout):
            # 判定：子程序已退出 → 記錄並結束等待
            if not proc.is_alive():
                msg_log().append_error_log(EVENT_FAILED, "Subprocess exited before first response")
                timed_out = False
                break

            # 判定：使用者已取消 → 記錄並結束等待
            if msg_main().get_msg_status() == "cancelled":
                msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — stop waiting")
                timed_out = False
                break

            # 即時等子程序事件（逾時 1 秒＝本 tick 無事件）
            event_name = msg_state_machine().wait_event(1.0)

            # 判定：本 tick 無事件 → 記錄等待進度
            if event_name == "":
                msg_log().append_info_log(
                    EVENT_WAITING_FOR_RESPONSE,
                    f"Waiting for first response : ({sec + 1}/{connection_timeout}s)...", meta={"sec": sec + 1, "total_sec": connection_timeout})
            else:
                # 子程序事件（子程序已自行套用狀態）→ 父程序只需結束等待
                # 判定：首個回應已到達 → 結束等待
                first_response_arrived = True
                timed_out = False
                break

        # 判定：迴圈未因「到達／早亡／取消」結束 → 逾時（記錄）
        if timed_out:
            msg_log().append_warn_log(EVENT_CONNECT_TIMEOUT, f"Connect timeout ({connection_timeout}s)", meta={"total_sec": connection_timeout})

        return first_response_arrived

    def _finalize(self, proc) -> None:
        """依「現時狀態＋exitcode＋response」決定終態（狀態已代表程序所在階段）。"""
        # 程序狀態（自我心跳）：子程序已結束 → 撤掉它的狀態檔（worker 為代寫者——不需等程序退出）
        if proc.pid is not None:
            process_status.unregister("goose", proc.pid)

        # 清除 registry 中的 child PID（已停用——registry 由 daemon 統一管理；worker 結束時由 daemon 回收）
        # chat_id = msg_main().get_chat_id()
        # if proc.pid is not None:
        #     daemon().remove_child_pid(chat_id, proc.pid)

        # 判定：status 檔不存在（PHP 已歸檔移走）→ 直接返回（避免誤判 queued 重建孤兒檔）
        if not msg_main().get_msg_status_path().exists():
            return

        current_state = msg_main().get_msg_status()

        # 判定：已是終態（取消／子程序已回報失敗）→ 不覆寫
        if msg_state_machine().is_terminal_status(current_state):
            return

        # 判定：已進入回覆階段 → 依 exitcode 與 response 內容決定
        if current_state == "replying":
            # 判定：子程序異常結束 → 串流中斷
            if proc.exitcode != 0:
                self._transition_status(EVENT_FAILURE_RECORDED, "Stream interrupted")
            # 判定：answer 檔有字＝回覆完成
            elif self._has_response_result():
                self._transition_status(EVENT_COMPLETED, "Completed")
            # answer 空 → 失敗
            else:
                self._transition_status(EVENT_FAILURE_RECORDED, "Stream completed but result is empty")
        # 判定：未進入回覆階段（connecting／summarizing）→ 失敗（原因已於偵測點記錄）
        else:
            self._transition_status(EVENT_FAILED, "Failed before reply")

    def _finalize_warm(self) -> None:
        """暖模式收尾：依「現時狀態＋回應」決定終態（無 exitcode——例外已視作正常結束）。"""
        # 判定：status 檔不存在（PHP 已歸檔移走）→ 直接返回（避免誤判 queued 重建孤兒檔）
        if not msg_main().get_msg_status_path().exists():
            return

        current_state = msg_main().get_msg_status()

        # 判定：已是終態（取消／失敗已回報）→ 不覆寫
        if msg_state_machine().is_terminal_status(current_state):
            return

        # 判定：已進入回覆階段 → 依回應內容決定
        if current_state == "replying":
            # 判定：answer 檔有字＝回覆完成
            if self._has_response_result():
                self._transition_status(EVENT_COMPLETED, "Completed")
            # answer 空 → 失敗
            else:
                self._transition_status(EVENT_FAILURE_RECORDED, "Stream completed but result is empty")
        # 判定：未進入回覆階段（connecting／summarizing）→ 失敗（原因已於偵測點記錄）
        else:
            self._transition_status(EVENT_FAILED, "Failed before reply")

    # result 檔有無字（收尾判準——語義答案；索引制——任一段有字即完成）
    def _has_response_result(self) -> bool:
        # 逐個 result 檔（自然序）查
        for response_result_path_item in msg_main().get_response_result_paths():
            # 判定：有字 → 即完成
            if response_result_path_item.read_text(encoding="utf-8").strip() != "":
                return True

        return False

    def finalize_abandoned_message(self, msg_conf_path: Path) -> None:
        """被遺棄訊息收終態（daemon 用——暖 worker 死於工單中時，防條訊息卡死等人救）。"""
        msg_main().load_msg_conf_data(msg_conf_path)

        # 判定：status 檔不存在（PHP 已歸檔移走）→ 直接返回
        if not msg_main().get_msg_status_path().exists():
            return

        # 判定：已是終態（已有人收尾）→ 不覆寫
        if msg_state_machine().is_terminal_status(msg_main().get_msg_status()):
            return

        # 失敗收尾（worker 不在了——錯誤記入該訊息 msg_log）
        self._transition_status(EVENT_FAILED, "Warm worker died mid-job")

    def _wait_subprocess_ready(self, proc, startup_timeout_sec: int) -> bool:
        """只觀察「子程序是否就緒」；True＝已就緒（False＝逾時／早亡／取消）。"""
        subprocess_ready = False
        # 逾時旗標（預設逾時；非逾時結束——就緒／早亡／取消——時改 False）
        timed_out = True

        # 逐秒等待（直到就緒／早亡／取消／逾時）
        for sec in range(startup_timeout_sec):
            # 判定：子程序已退出 → 記錄並結束等待
            if not proc.is_alive():
                msg_log().append_error_log(EVENT_FAILED, "Subprocess exited before ready")
                timed_out = False
                break

            # 判定：使用者已取消 → 記錄並結束等待
            if msg_main().get_msg_status() == "cancelled":
                msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — stop waiting")
                timed_out = False
                break

            # 即時等子程序事件（逾時 1 秒＝本 tick 無事件）
            event_name = msg_state_machine().wait_event(1.0)

            # 判定：本 tick 無事件 → 記錄等待進度
            if event_name == "":
                msg_log().append_info_log(
                    EVENT_SUBPROCESS_INITIALIZING,
                    f"Waiting subprocess ready: ({sec + 1}/{startup_timeout_sec}s)...", meta={"sec": sec + 1, "total_sec": startup_timeout_sec})
            else:
                # 子程序事件（子程序已自行套用狀態）→ 父程序只需判斷就緒
                # 判定：就緒事件 → 結束等待
                if event_name == EVENT_SUBPROCESS_READY:
                    subprocess_ready = True
                    timed_out = False
                    break

        # 判定：迴圈未因「就緒／早亡／取消」結束 → 逾時（記錄）
        if timed_out:
            msg_log().append_error_log(
                EVENT_SUBPROCESS_STARTUP_TIMEOUT,
                f"Subprocess startup timeout ({startup_timeout_sec}s)", meta={"total_sec": startup_timeout_sec})

        return subprocess_ready

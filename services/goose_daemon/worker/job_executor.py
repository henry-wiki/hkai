#!/usr/bin/env python3
"""
job_executor.py

查詢四檔（msg_*）的流程處理（job_executor_class singleton——get_instance() 取得）。

    data/messages/polling/{project_slug}/{chat_sid}/{prompt_id}/meta.json   找到 → 處理（raw 行經 session 寫 job_stdout）
    → 完成/失敗 → status 寫入 msg_status（留 polling——PHP poll 收尾移 archive）

任何情況（成功/錯誤/例外）都要標記終態，避免卡檔；archive 目錄為純歸檔（PHP 收尾移入），本模組永不讀取。

連線狀態機（status 寫入 msg_status().txt 單行字串——poll 讀取；msg_conf 純設定永不修改；message_log 記錄完整事件）：
    queued（建檔獲派工）→ connecting（子程序啟動＋等待首 chunk）→ summarizing（摘要串流——契約保留，本agent唔產生）→ replying（主回答）→ completed / failed（留 polling 待 PHP 收尾）

執行模式（只有暖路徑）：暖 worker 同程序執行 execute_job（取消／逾時由 daemon 監督兜底）
"""

from pathlib import Path
from typing import cast

from ..context import (
    entry,
    job_stdout,
    message_claim,
    message_log,
    message_status,
    message_store,
)
from .. import context as daemon_context
from bootstrap import env
from bootstrap import sc_logger
from ..runtime import process
from .message_events import (
    EVENT_COMPLETED,
    EVENT_FAILED,
    EVENT_FAILURE_RECORDED,
    EVENT_QUERY_DISPATCHED,
    EVENT_SUBPROCESS_SPAWNED,
)


class job_executor_class:
    """
    查詢處理（singleton——整個程序只有一個實例）。

    職責單一：單一 msg_conf 的完整處理流程（單次 spawn＋連線逾時兜底＋收尾狀態）；檔案讀寫一律走 message_store／job_stdout／message_claim 專責。
    """

    _instance: "job_executor_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "job_executor_class":
        """取得 job_executor_class 單例（整個程序共用同一實例）。"""
        return cls()

    def initialize(self, msg_conf_path: Path) -> bool:
        """載入單張工單語境（msg_conf → message_store 狀態；每次開工一次——單一載入點）。

        回 False＝唔使做單：①已終態（completed／failed／cancelled）；②stdout 已存在（之前開過工）；③msg_conf 嘅 content 空。
        """
        # 金鑰即時重讀（.env 為唯一真相——更新／清空下一條問題即生效）
        env.load_api_keys()

        message_store().load(msg_conf_path)

        # 讀當前狀態（直接讀 msg_conf 同目錄嘅 msg_status.txt）
        current_status = message_store().get_msg_status(msg_conf_path)

        # 判定：已終態 → 記 log＋即刻停（PHP poll 負責歸檔，Python 不重處理）
        if message_status().is_terminal_status(current_status):
            sc_logger.log("initialize: job skipped (status=" + current_status + ") path=" + str(msg_conf_path))
            return False

        # stdout 已存在＝呢張單之前已開過工（前一手 worker 中途死咗／兩隻工人搶同一張單／重派時舊檔未清）
        # 唔重做：新舊輸出會疊埋，答案會亂
        if job_stdout().exists():
            sc_logger.log("initialize: job skipped (stdout exists) path=" + str(msg_conf_path))
            return False

        # 讀使用者輸入（meta.json——prompt_text／content；msg_prompt.json 已退役）
        content = message_store().get_msg_prompt()

        # 附件（msg_conf.attachments——純圖片訊息：冇文字但有圖，仍然要交模型）
        attachments_conf = cast("list[dict[str, object]]", message_store().get_msg_conf_data().get("attachments", []))
        has_attachment   = len(attachments_conf) > 0

        # 判定：空內容且冇附件 → 標記完成＋記 log＋即刻停（留 polling，PHP poll 收尾移 archive）
        if not content.strip() and not has_attachment:
            sc_logger.log("initialize: job skipped (empty prompt) path=" + str(msg_conf_path))
            _ = message_status().apply(EVENT_COMPLETED)
            return False

        return True

    def process(self) -> bool:
        """
        做一張工單（呼叫前須先 initialize()——已篩走終態／重做／空 prompt）。

        回 True＝正常完成（工人留低）；False＝出錯（呼叫端解僱工人）。

        流程：

            認領（原子——message_claim；認領唔到即停）
            -> 狀態轉換：派工（→ queued）→ 就緒（→ connecting）
            -> execute_job（同程序阻塞至完成）
            -> 收尾（_finalize_warm 依現時狀態＋回應判終態）
        """

        # 認領本訊息（原子——同時只一個；先認領，認領唔到一個字都唔寫）
        if message_claim().claim(process.self_pid()) is False:
            sc_logger.log("claim failed: pid=" + str(process.self_pid()) + " owner=" + str(message_claim().get_owner_pid()) + " path=" + str(message_store().get_message_dir()))
            return True

        # 狀態轉換：派工（→ queued）
        _ = self.transition_status(EVENT_QUERY_DISPATCHED, "Dispatching to warm worker...")

        # worker 已就緒（暖 worker 常駐）——同一事件照發（→ connecting）
        _ = self.transition_status(EVENT_SUBPROCESS_SPAWNED, "Warm worker running")

        # 執行單次查詢（同程序——阻塞至完成；例外已由 execute_job 內部記錄）
        job_ok = entry().execute_job()

        # 收尾（依現時狀態決定終態）
        self._finalize_warm()

        return job_ok

    def transition_status(self, event: str, message: str = "") -> str:
        """狀態轉換單一出口：經 FSM 轉移（唯一 msg_status 寫入者）＋記對應 level 的 message_log。"""
        # 經 FSM（依 transitions 決定新狀態；未定義＝不改狀態）
        _next_state = message_status().apply(event)

        # 成功終態
        if _next_state == "completed":
            _ = message_log().append_success_log(event, message)
            message_log().complete_all_poll_files()
        # 失敗終態
        elif _next_state == "failed":
            _ = message_log().append_error_log(event, message)
            message_log().abort_all_poll_files()
        # 進行中（connecting／replying／summarizing…）
        else:
            _ = message_log().append_info_log(event, message)

        return _next_state

    def _finalize_warm(self) -> None:
        """暖模式收尾：依「現時狀態＋回應」決定終態（無 exitcode——例外已視作正常結束）。"""
        # 判定：status 檔不存在（PHP 已歸檔移走）→ 直接返回（避免誤判 queued 重建孤兒檔）
        if not message_store().get_msg_status_path().exists():
            return

        current_state = message_store().get_msg_status()

        # 判定：已是終態（取消／失敗已回報）→ 不覆寫
        if message_status().is_terminal_status(current_state):
            return

        # 判定：已進入回覆階段 → 依回應內容決定
        if current_state == "replying":
            # 判定：answer 檔有字＝回覆完成
            if self._has_response_result():
                # 判定：回覆含 provider 錯誤 → 標 failed（訊息用我哋嘅顯示字）
                error_found, error_display_text = self._normalize_error_results()

                if error_found is True:
                    _ = self.transition_status(EVENT_FAILED, error_display_text)
                else:
                    _ = self.transition_status(EVENT_COMPLETED, "Completed")
            # answer 空 → 先睇有冇錯誤原文（錯誤回覆唔會寫 result）
            else:
                error_found, error_display_text = self._normalize_error_results()

                # 判定：有錯誤原文 → 用我哋嘅顯示字標 failed
                if error_found is True:
                    _ = self.transition_status(EVENT_FAILED, error_display_text)
                else:
                    _ = self.transition_status(EVENT_FAILURE_RECORDED, "Stream completed but result is empty")
        # 判定：未進入回覆階段（connecting／summarizing）→ 失敗（原因已於偵測點記錄）
        else:
            _ = self.transition_status(EVENT_FAILED, "Failed before reply")

    # result 檔有無字（收尾判準——語義答案；索引制——任一段有字即完成）

    def _write_failure_result_text(self, raw_text: str) -> None:
        """失敗時寫一段可見錯誤文字（result 檔；顯示用）。"""
        from . import error_text as _error_text

        _error_code, display_text, matched = _error_text.classify_error(raw_text)

        # 判定：認唔出錯誤類型 → 原文照顯示（唔假裝成功）
        if matched is False:
            display_text = raw_text.strip()

        response_index_list = [item for item in range(0, 1)]

        for response_index in response_index_list:
            _ = message_log().append_response_result(response_index, display_text)

    def _normalize_error_results(self) -> tuple[bool, str]:
        """錯誤原文存在 → 寫 msg_error.json（卡資料）＋agent-errors.log；回 True＝有錯誤。"""
        import datetime
        import json as _json

        from . import error_text as _error_text

        raw_text = message_log().get_error_detail_text()

        # 判定：冇錯誤原文 → 唔係錯誤
        if raw_text.strip() == "":
            return False, ""

        code, display_text, matched = _error_text.classify_error(raw_text)
        message_title = "回覆失敗"

        # 判定：認唔出 → 用原文做訊息（唔假裝成功）
        if matched is False:
            display_text = raw_text.strip()

        error_payload = {
            "code": code,
            "title": message_title,
            "message": display_text,
            "detail": raw_text,
            "detected_at": datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        }

        # 錯誤文字寫入 result 段（回覆結果——reload 仍顯示；已有字就唔重複寫）
        existing_result_text = ""

        for response_result_path_item in message_store().get_response_result_paths():
            existing_result_text += response_result_path_item.read_text(encoding="utf-8")

        # 判定：result 未有錯誤字 → 寫一次
        # 判定：未寫過錯誤回覆（旗標檔保證只寫一次）
        error_written_flag = message_store().get_message_dir() / "msg_error_written.flag"

        if error_written_flag.exists() is False and "[E_" not in existing_result_text:
            error_written_flag.write_text("1", encoding="utf-8")
            result_display_text = error_payload["message"].replace("\\n", "\n")
            _ = message_log().append_response_result(0, result_display_text)


        _error_text.append_agent_error_log(
            str(daemon_context.DATA_DIR) + "/logs/agent-errors.log",
            message_store().get_message_id(),
            "",
            message_store().get_model_name(),
            raw_text,
            code,
        )

        return True, error_payload["message"]

    def _has_response_result(self) -> bool:
        # 逐個 result 檔（自然序）查
        for response_result_path_item in message_store().get_response_result_paths():
            # 判定：有字 → 即完成
            if response_result_path_item.read_text(encoding="utf-8").strip() != "":
                return True

        return False

    def finalize_abandoned_message(self, msg_conf_path: Path) -> None:
        """被遺棄訊息收終態（daemon 用——暖 worker 死於工單中時，防條訊息卡死等人救）。"""
        # 判定：工單目錄已消失（PHP 已搬 archive／已刪）→ 唔收尾（一個字都唔寫）
        if Path(msg_conf_path).exists() is False:
            return

        _ = self.initialize(msg_conf_path)

        # 判定：status 檔不存在（PHP 已歸檔移走）→ 直接返回
        if not message_store().get_msg_status_path().exists():
            return

        # 判定：已是終態（已有人收尾）→ 不覆寫
        if message_status().is_terminal_status(message_store().get_msg_status()):
            return

        # 失敗收尾（worker 不在了——錯誤記入該訊息 message_log）
        _ = self.transition_status(EVENT_FAILED, "Warm worker died mid-job")
        # 失敗時寫入可見錯誤文字（畫面顯示原因；完整原文另存 error log）
        _ = self._write_failure_result_text("Warm worker died mid-job")

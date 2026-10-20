#!/usr/bin/env python3
"""
msg_main.py

msg 檔路徑／掃描／jobs 邏輯（msg_main_class singleton——get_instance() 取得）。

對應 PHP 的 sc_ai_chats_message_poll_main_Class——路徑 bundle／掃描／單一路徑／jobs 清單（目錄制 messages/polling/{project_slug}/{message_id}/msg_{type}.{ext}）。
"""

import json
from pathlib import Path
from typing import ClassVar

class msg_main_class:
    """
    msg 檔的路徑／掃描／jobs（singleton——整個程序只有一個實例）。

    職責單一：msg_* 檔案的「路徑組裝＋掃描＋jobs 清單」；不含任何處理流程。
    """

    _instance: "msg_main_class | None" = None
    _chat_id: str
    _message_id: str

    # type → 副檔名（類別級常數——ClassVar 標明非實例狀態——與 PHP poll_filetype 一致）
    _polling_msg_file_type: ClassVar[dict[str, str]] = {
        "conf": "json",
        "status": "txt",
        "prompt": "json",
        "stdout": "txt",       # 純紀錄（原 response）
        "response_thinking": "txt",
        "response_result": "txt",
        "log": "txt",
        "stderr": "txt",
        "claim": "json",        # 處理認領（原子）
        "error": "json",
        "stream_log": "jsonl",
        "error_log": "jsonl",
        "poll_list": "jsonl",
        "summarize": "txt",
        "summarize_result": "txt",
        "summarize_thinking": "txt",
        "permission": "json",           # 權限請求（等待交 client——PHP 讀出提示卡）
        "permission_decision": "json",  # 權限決定（client 讀——PHP 寫）
    }

    def __new__(cls):
        """單例：整個程序只建立一次（無狀態——單例為統一入口）。"""
        # 已存在實例 → 直接回傳（單一實例共享）
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    def __init__(self):
        """實例化時注入默認值（僅首次生效）。"""
        if hasattr(self, "_chat_id"):
            return
        self._chat_id = ""
        self._message_id = ""
        self._msg_conf_data: dict = {

            "project_id"    : "",
            "chat_id"       : "",
            "message_id"    : "",

            "request_timeout" : 30,
            "connection_timeout" : 600,
            "startup_timeout" : 30

        }

    @classmethod
    def get_instance(cls) -> "msg_main_class":
        """取得 msg_main_class 單例（整個程序共用同一實例）。"""
        return cls()

    def init_msg_conf_data(self, msg_conf_data: dict) -> None:
        """整包覆蓋 msg_conf_data ＋同步 chat_id/message_id（語境設定的統一入口）。"""
        self._msg_conf_data = msg_conf_data
        self._chat_id = self._msg_conf_data["chat_id"]
        self._message_id = self._msg_conf_data["message_id"]

    def load_msg_conf_data(self, msg_conf_path: Path) -> None:
        """讀取 msg_conf 檔 → 與默認值合併（載入覆蓋默認）→ 存入 self。"""
        try:
            data = json.loads(msg_conf_path.read_text(encoding="utf-8"))
            if isinstance(data, dict):
                merged_conf = {**self._msg_conf_data, **data}
                self.init_msg_conf_data(merged_conf)
        except Exception:  # noqa: BLE001, S110
            pass

    def get_chat_id(self):
        return self._chat_id

    def get_message_id(self):
        return self._message_id

    def get_messages_dir(self) -> str:
        """取得 daemon_messages_dir 路徑（從 daemon_manager 取得）。"""
        from ..context import daemon
        return daemon().get_daemon_conf_data()["daemon_messages_dir"]

    def get_msg_conf_data(self) -> dict:
        """回傳目前已載入的 msg_conf 內容（load_msg_conf_data 時已讀入）。"""
        return self._msg_conf_data

    def get_project_slug(self) -> str:
        """本輪 job 的 project_slug（msg_conf 帶入——目錄命名用）。"""
        return str(self._msg_conf_data.get("project_slug", "")).strip()

    def get_message_dir(self, message_id: str) -> Path:
        """
        訊息目錄：messages/polling/{project_slug}/{message_id}（project_slug 由 msg_conf 帶入）。

        polling 已唔存在（PHP 收尾已移歸檔）但歸檔存在 → 用歸檔目錄——
        遲到寫入／讀取（收尾後嘅終態記錄）唔會漏、亦唔會另開 poll 目錄。
        """
        message_dir = Path(self.get_messages_dir()) / self.get_project_slug() / str(message_id).strip()

        # 判定：polling 目錄存在 → 直接使用（現行行為）
        if message_dir.exists():
            return message_dir

        # 判定：polling 冇 → 查歸檔目錄（已歸檔訊息——記錄要落返同一批檔）
        archived_message_dir = Path(self.get_archived_messages_dir()) / self.get_project_slug() / str(message_id).strip()
        if archived_message_dir.exists():
            return archived_message_dir

        # 兩者皆無 → 原 polling 路徑（新建者——維持既有語義）
        return message_dir

    def get_archived_messages_dir(self) -> str:
        """取得 daemon_archive_dir 路徑（從 daemon_manager 取得——歸檔訊息目錄）。"""
        from ..context import daemon
        return daemon().get_daemon_conf_data()["daemon_archive_dir"]

    def get_response_result_paths(self) -> list:
        """全部 response result 檔路徑（msg_response.{n}.result.txt——按 index 自然序；收尾判準用）。"""
        message_dir = self.get_message_dir(self._message_id)

        response_result_paths = []
        for response_result_path_item in message_dir.glob("msg_response.*.result.txt"):
            response_result_paths.append(response_result_path_item)

        # 自然序（按 index 數字——唔係字典序）
        response_result_paths.sort(key=lambda response_result_path_item: int(response_result_path_item.name.split(".")[1]))
        return response_result_paths

    def get_msg_summarize_path(self) -> Path:
        return self.get_msg_file_path("summarize", self._message_id)

    def get_msg_prompt_path(self) -> Path:
        return self.get_msg_file_path("prompt", self._message_id)

    def get_msg_status_path(self) -> Path:
        return self.get_msg_file_path("status", self._message_id)

    def get_msg_status(self) -> str:
        """讀取 msg_status（取最後一個非空行——append 寫入；缺失/損壞/未知回空字串）。"""
        try:
            text = self.get_msg_status_path().read_text(encoding="utf-8")
        except Exception:  # noqa: BLE001
            return ""

        _status = ""
        # 由後往前取第一個非空行（append 寫入——最新狀態在最後一行）
        for _line in reversed(text.splitlines()):
            # 判定：非空行 → 即最新狀態
            if _line.strip():
                _status = _line.strip()
                break

        # 契約 status 清單（fsm_map——經 msg_state_machine；延遲 import 避循環）
        from ..context import msg_state_machine
        status_values = msg_state_machine().get_status_values()

        # 判定：唔屬契約 status → 回空
        if _status not in status_values:
            return ""
        return _status

    def get_msg_prompt(self) -> str:
        """讀取 msg_prompt 檔的「問題內容」（JSON 格式；不存在／損壞 = 空字串）。"""
        prompt_path = self.get_msg_prompt_path()
        if not prompt_path.exists():
            return ""
        try:
            prompt_data = json.loads(prompt_path.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return ""
        # 判定：非 dict → 空字串
        if not isinstance(prompt_data, dict):
            return ""
        return prompt_data.get("content", "")

    def set_msg_prompt_input_token(self, input_token: int) -> None:
        """更新 msg_prompt 的 input_token（保留 content 與 complete_token）。"""
        prompt_path = self.get_msg_prompt_path()

        # 判定：檔案不存在 → 不動作
        if not prompt_path.exists():
            return

        try:
            prompt_data = json.loads(prompt_path.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return

        # 判定：非 dict → 不動作
        if not isinstance(prompt_data, dict):
            return

        prompt_data["input_token"] = int(input_token)
        prompt_path.write_text(json.dumps(prompt_data, ensure_ascii=False), encoding="utf-8")

    def set_msg_status(self, status: str) -> None:
        """寫入 msg_status（append——每次寫入新增一行；最新狀態為最後一行）。"""
        with self.get_msg_status_path().open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(status + "\n")

    def get_msg_conf_files(self) -> list[Path]:
        """掃描 messages/polling/*/*/msg_conf.json → 按建立時間排序回傳路徑清單（最舊優先）。"""
        from ..context import daemon
        daemon_messages_dir = daemon().get_daemon_conf_data()["daemon_messages_dir"]
        return sorted(
            Path(daemon_messages_dir).glob("*/*/msg_conf.json"),
            key=lambda p: p.stat().st_birthtime,
        )

    # 單一 type 路徑（目錄制：messages/polling/{project_slug}/{message_id}/msg_{type}.{ext}——純組裝，不依賴檔案存在）
    def get_msg_file_path(self, msg_type: str, message_id: str) -> Path:
        """
        單一 type 檔路徑：messages/polling/{project_slug}/{message_id}/msg_{type}.{ext}（與 PHP get_poll_file 同一規則）。
        """

        file_extension = "txt"
        if msg_type in self._polling_msg_file_type:
            file_extension = self._polling_msg_file_type[msg_type]

        return self.get_message_dir(message_id) / f"msg_{msg_type}.{file_extension}"


# 模組級實例已移除——改由 context.py 的 msg_main() bridging 函數取得單例

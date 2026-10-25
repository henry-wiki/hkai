#!/usr/bin/env python3
"""
message_store.py

msg 檔路徑／掃描／jobs 邏輯（message_store_class singleton——get_instance() 取得）。

對應 PHP 的 sc_ai_chats_message_poll_main_Class——路徑 bundle／掃描／單一路徑／jobs 清單（目錄制 messages/polling/{project_slug}/{message_id}/msg_{type}.{ext}）。
"""

import json
from pathlib import Path

from .. import context
from ..utils import io
from . import message_context


class message_store_class:
    """
    msg 檔的路徑／掃描／jobs（singleton——整個程序只有一個實例）。

    職責單一：msg_* 檔案的「路徑組裝＋掃描＋jobs 清單」；不含任何處理流程。
    """

    _instance: "message_store_class | None" = None
    _chat_id: str
    _chat_sid: str
    _message_id: str
    _message_dir: Path
    _claim_path: Path

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
        self._project_id = ""
        self._project_slug = ""
        self._chat_id = ""
        self._chat_sid = ""
        self._message_id = ""
        self._msg_conf_data: dict = {

            "project_id"    : "",
            "project_slug"  : "",
            "chat_id"       : "",
            "message_id"    : "",

            "request_timeout" : 30,
            "connection_timeout" : 600,
            "startup_timeout" : 30

        }

    @classmethod
    def get_instance(cls) -> "message_store_class":
        """取得 message_store_class 單例（整個程序共用同一實例）。"""
        return cls()

    def init_msg_conf_data(self, msg_conf_data: dict) -> None:
        """整包覆蓋 msg_conf_data ＋同步 chat_id/message_id（語境設定的統一入口）。"""
        self._msg_conf_data     = msg_conf_data

        self._project_id        = str(self._msg_conf_data["project_id"]).strip()
        self._project_slug      = str(self._msg_conf_data["project_slug"]).strip()
        self._chat_id           = str(self._msg_conf_data["chat_id"]).strip()
        self._chat_sid          = str(self._msg_conf_data.get("chat_sid", "")).strip()
        self._message_id        = str(self._msg_conf_data["message_id"]).strip()

        self._project_path      = str(self._msg_conf_data.get("project_path", "")).strip()
        self._model_name        = str(self._msg_conf_data.get("model_name", "")).strip()
        self._chat_mode         = str(self._msg_conf_data.get("chat_mode", "")).strip()
        self._context_length    = self._msg_conf_data.get("context_length", None)
        self._reasoning_effort  = str(self._msg_conf_data.get("reasoning_effort", "")).strip()

        self.init_msg_paths()

    def init_msg_paths(self) -> None:
        """由已注入嘅 project_slug／message_id 算好本張工單路徑（目錄＋認領檔）。"""
        self._message_dir   = context.MESSAGES_POLLING_DIR / self._project_slug / self._message_id
        self._claim_path    = message_context.get_msg_file_path(self.get_message_dir(), "claim")

    def load(self, msg_conf_path: Path) -> None:
        """讀取 msg_conf 檔 → 與默認值合併（載入覆蓋默認）→ 存入 self。"""
        try:
            data = json.loads(msg_conf_path.read_text(encoding="utf-8"))
            if isinstance(data, dict):
                merged_conf = {**self._msg_conf_data, **data}
                self.init_msg_conf_data(merged_conf)
        except Exception:  # noqa: BLE001, S110
            pass

    def get_chat_id(self) -> str:
        return self._chat_id

    def get_chat_sid(self) -> str:
        """本輪對話嘅 chat_sid（msg_conf 帶入；新對話未建立＝空字串）。"""
        return self._chat_sid

    def get_message_id(self) -> str:
        return self._message_id

    def get_msg_conf_data(self) -> dict:
        """回傳目前已載入的 msg_conf 內容（load 時已讀入）。"""
        return self._msg_conf_data

    def get_project_slug(self) -> str:
        """本輪 job 的 project_slug（init_msg_conf_data 時已注入——目錄命名用）。"""
        return self._project_slug

    def get_project_path(self) -> str:
        """本輪 job 的 project_path（msg_conf 帶入——goose 工作目錄）。"""
        return self._project_path

    def get_model_name(self) -> str:
        """本輪 job 的 model_name（provider/model——引擎解析用）。"""
        return self._model_name

    def get_chat_mode(self) -> str:
        """本輪 job 的 chat_mode（UI 模——引擎對映 goose 模式）。"""
        return self._chat_mode

    def get_context_length(self):
        """本輪 job 的 context_length（msg_conf 帶入；缺＝None）。"""
        return self._context_length

    def get_reasoning_effort(self) -> str:
        """本輪 job 的 reasoning_effort（我們自己決定；空＝引擎用默認）。"""
        return self._reasoning_effort

    def get_message_dir(self) -> Path:
        """訊息目錄（init_msg_paths 已算好：messages/polling/{project_slug}/{message_id}）。"""
        return self._message_dir

    def get_response_result_paths(self) -> list:
        """全部 response result 檔路徑（msg_response.{n}.result.txt——按 index 自然序；收尾判準用）。"""
        message_dir = self.get_message_dir()

        response_result_paths = list(message_dir.glob("msg_response.*.result.txt"))

        # 自然序（按 index 數字——唔係字典序）
        response_result_paths.sort(key=lambda response_result_path_item: int(response_result_path_item.name.split(".")[1]))
        return response_result_paths

    def get_msg_summarize_path(self) -> Path:
        return message_context.get_msg_file_path(self.get_message_dir(), "summarize")

    def get_claim_path(self) -> Path:
        """認領檔路徑（init_msg_paths 已算好）。"""
        return self._claim_path

    def get_msg_prompt_path(self) -> Path:
        return message_context.get_msg_file_path(self.get_message_dir(), "prompt")

    def get_msg_prompt(self) -> str:
        """讀 msg_prompt.json 嘅 content（使用者輸入原樣；缺失／損壞回空字串）。"""
        prompt_data = io.read_json(self.get_msg_prompt_path())

        # 判定：讀唔到（缺失／損壞）→ 空字串
        if prompt_data is None:
            return ""

        return str(prompt_data.get("content", ""))

    def get_msg_status_path(self) -> Path:
        return message_context.get_msg_file_path(self.get_message_dir(), "status")

    def get_msg_status(self, msg_conf_path: Path | None = None) -> str:
        """讀 msg_status 最後一個非空行（append 寫入；缺失／損壞回空字串）。

        msg_conf_path 有值 → 由該目錄直接推 msg_status.txt（唔使載入 msg_conf）；
        無值 → 用目前已載入嘅 message_id。
        """
        status_path = self.get_msg_status_path()

        # 判定：有 msg_conf 路徑 → 同目錄嘅 msg_status.txt
        if msg_conf_path is not None:
            status_path = Path(msg_conf_path).parent / "msg_status.txt"

        return io.get_last_non_empty_line(status_path)

    def set_msg_status(self, status: str) -> None:
        """寫入 msg_status（append——每次寫入新增一行；最新狀態為最後一行）。"""
        with self.get_msg_status_path().open("a", encoding="utf-8", newline="") as output_file:
            output_file.write(status + "\n")

    def get_msg_conf_files(self) -> list[Path]:
        """掃描 messages/polling/*/*/msg_conf.json → 按建立時間排序回傳路徑清單（最舊優先）。"""
        return sorted(
            Path(context.MESSAGES_POLLING_DIR).glob("*/*/msg_conf.json"),
            key=lambda p: p.stat().st_birthtime,
        )



# 模組級實例已移除——改由 context.py 的 message_store() bridging 函數取得單例

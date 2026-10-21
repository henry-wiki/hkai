#!/usr/bin/env python3
"""
goose_entry.py

goose 執行入口（goose_entry_class singleton——get_instance() 取得）。

兩種用法：
    冷路徑 run_coder（每訊息一子程序——spawn target）
    暖路徑 preload＋run_job（常駐 worker——開機預載一次，每工單呼叫 run_job）

流程：語境恢復 → session 同步（checkpoints——有變／首次＝全量重建）→ goose 環境組裝（provider／model 由 model_name 解析）→ 傳輸分流 → 收尾：
      cli（預設）：goose run 子程序（--output-format stream-json）→ 逐行翻譯
      （thinking／text 分流寫 response 段檔＋FSM 事件；raw 行原樣寫 msg_stdout）。
      acp：goose acp 子程序（stdio 協定——engine/acp_client.py 驅動；權限請求經檔案契約互動）。

與 aider_daemon 的引擎路對位——輸出契約（response 段檔／msg_stdout／
FSM 事件）一致；引擎由 goose 執行檔承擔（無內嵌 litellm／aider）。

session 續話：每對話一條 goose session（名＝hkai-chat-{chat_id}）——
首條訊息建立、往後 --resume 續（同名重複建立會產生重複 session，故先查清單判定）。

資料根：每專案一個 GOOSE_PATH_ROOT——<storage>/localdb/goose/<project_slug>；
session／config／state 全歸該專案資料夾（專案間零干涉）。
config 之下＝.goosehints（系統指示）＋custom_providers/<前綴>.json（自訂 provider 檔）——皆於每問 spawn 前刷新。
"""

import asyncio
import json
import os
import queue
import shutil
import subprocess
import sys
import threading
import time
from pathlib import Path

import psutil

from ..context import *
from ..msg.msg_events import *
from ..runtime import sc_logger

from .auth_setup import api_key_loader_class


# 權限請求等待上限（秒——無人決定即自動拒；本側代碼常數——暫唔 expose 喺 hkai_config.json）
PERMISSION_WAIT_TIMEOUT_SEC = 60

# 權限決定輪詢間隔（秒——ACP 權限等待每 tick 檢查一次決定檔）
PERMISSION_DECISION_POLL_INTERVAL_SEC = 0.4


# 自訂 provider 表（model_name 前綴 → goose 原生唔識嘅 provider——每次發問寫
# <GOOSE_PATH_ROOT>/config/custom_providers/<前綴>.json；新增 provider＝加一筆）
CUSTOM_PROVIDER_TABLE = {
    "kilo": {
        "display_name": "Kilo",
        "description":  "Kilo AI Gateway",
        "api_key_env":  "KILO_API_KEY",
        "base_url":     "https://api.kilo.ai/api/gateway/chat/completions",
    },
    "deepseek": {
        "display_name": "DeepSeek",
        "description":  "DeepSeek API",
        "api_key_env":  "DEEPSEEK_API_KEY",
        "base_url":     "https://api.deepseek.com/chat/completions",
    },
}



class goose_entry_class:
    """
    執行入口（singleton——整個程序只有一個實例）。

    職責單一：建立 goose 執行環境（provider 環境＋資料根＋執行檔＋session）並執行單次查詢；不含重試／狀態機。
    """

    _instance: "goose_entry_class | None" = None

    def __new__(cls):
        """單例：整個程序只建立一次。"""
        if cls._instance is None:
            cls._instance = super().__new__(cls)
        return cls._instance

    @classmethod
    def get_instance(cls) -> "goose_entry_class":
        """取得 goose_entry_class 單例。"""
        return cls()

    def preload(self) -> None:
        """
        預載檢查（暖 worker 開機一次）：goose 為外部執行檔、每工單一子程序——
        無重型引擎可預載；僅驗執行檔可解析（缺＝記 log；實際錯誤留待各工單回報）。
        """
        goose_executable = self._resolve_goose_executable()

        # 判定：執行檔缺席 → 記 log（每工單再遇即失敗——此處不阻斷開機）
        if goose_executable == "":
            sc_logger.log("preload: goose executable not found (config/PATH)")
        else:
            sc_logger.log("preload: goose executable = " + goose_executable)

    def run_coder(self, daemon_conf_path: str, msg_conf_path: str, state_channel) -> None:
        """
        冷路徑子程序入口（spawn target——每訊息一程序）：重初始化 → 綁通道 → 執行單次工單。
        暖模式下不走此入口（暖 worker 直接呼叫 run_job）。
        """
        # 讀 runtime.json（is_reload=True：跳過 PID + keys，只讀 config + cwd）
        daemon().load_daemon_conf_data(Path(daemon_conf_path), is_reload=True)

        # 綁定狀態事件通道（FSM 為唯一狀態管理者；子程序自行套用並回報）
        msg_state_machine().attach_channel(state_channel)

        # 執行單次工單（與暖模式同一核心——run_job）
        self.run_job(msg_conf_path)

    def run_job(self, msg_conf_path: str) -> None:
        """
        單次查詢（暖 worker 每張工單一次）：組環境 → spawn goose → 串流翻譯 → 收尾。
        與冷路徑同一流程——差別只在「有無跨程序通道」。
        """
        # 讀 msg_conf.json（每張工單重新載入——語境切換）
        msg_main().load_msg_conf_data(Path(msg_conf_path))

        # 金鑰即時重讀（.env 為唯一真相——更新／清空下一條問題即生效）
        daemon_conf_data = daemon().get_daemon_conf_data()
        oauth_keys_path  = str(daemon_conf_data.get("daemon_oauth_keys_path", "") or "")

        # 判定：有路徑 → 重讀
        if oauth_keys_path != "":
            api_key_loader_class.get_instance().reload(Path(oauth_keys_path))

        # 取得語境（從載入後的 msg_main 狀態）
        chat_id    = msg_main().get_chat_id()
        message_id = msg_main().get_message_id()
        content    = msg_main().get_msg_prompt()

        # msg_conf 隨身資料（cwd／模型——PHP 寫入）
        msg_conf_data = msg_main().get_msg_conf_data()
        project_path  = str(msg_conf_data.get("project_path", ""))
        model_name    = str(msg_conf_data.get("model_name", ""))

        # msg_stdout 檔路徑（純紀錄——原樣輸出）
        msg_stdout_file: Path = msg_main().get_msg_file_path("stdout", message_id)

        # goose 子程序把手（finally 收尾用）
        goose_proc = None

        try:
            # 環境組裝（provider／model 由 model_name 解析——唔支持＝None）
            goose_env = self._build_goose_env(model_name)

            # 判定：model_name 唔支持（非 openrouter、亦唔喺自訂 provider 表）→ 記錯誤收工
            if goose_env is None:
                msg_log().append_error_log(
                    EVENT_FAILURE_RECORDED,
                    "Unsupported model for goose engine (openrouter/ or custom provider table)",
                    {"model_name": model_name},
                )
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
                return

            # 判定：key 未載入（oauth keys 檔缺對應 _API_KEY）→ 記錯誤收工
            api_key_env_name = self._resolve_api_key_env_name(model_name)
            if not goose_env.get(api_key_env_name, ""):
                msg_log().append_error_log(
                    EVENT_FAILURE_RECORDED,
                    api_key_env_name + " missing (oauth keys file)",
                )
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
                return

            # goose 執行檔（缺＝記錯誤收工）
            goose_executable = self._resolve_goose_executable()

            # 判定：執行檔缺席 → 記錯誤收工
            if goose_executable == "":
                msg_log().append_error_log(
                    EVENT_FAILURE_RECORDED,
                    "goose executable not found (external_paths.goose_executable / PATH)",
                )
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
                return

            # 工作目錄（缺／唔存在＝記錯誤收工——goose 要真實專案根）
            if project_path == "" or not Path(project_path).is_dir():
                msg_log().append_error_log(
                    EVENT_FAILURE_RECORDED,
                    "project path missing: " + project_path,
                    {"project_path": project_path},
                )
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)
                return

            # session model 對齊（換咗 model／provider＝更新 session 記錄＋全量重建訊息——出去嘅請求即全新；唔受 checkpoints 開關影響）
            session_sync().sync_model_switch_before_prompt()

            # session 同步（checkpoints 快照——有變／首次＝全量重建；資料夾唔存在＝功能未開、原路徑直去）
            session_sync().sync_before_prompt()

            # 對話 session（每對話一條——首條建立、往後續）
            goose_session_name = "hkai-chat-" + str(chat_id)

            # goose 資料根（每專案一個資料夾——session／config／state 全歸該專案；專案間零干涉）
            goose_env["GOOSE_PATH_ROOT"] = self._resolve_goose_path_root(project_path)

            # 系統指示（hkai——發問前注入：寫 goose 原生 Global Hints 檔；缺必要檔＝掟錯，外層記失敗）
            self._write_goose_hints_file(goose_env["GOOSE_PATH_ROOT"])

            # 上下文長度（msg_conf——model store 數字；goose auto compact 以此計閾值）
            context_length = msg_conf_data.get("context_length", None)

            # 判定：有值 → 落子程序環境（CLI／ACP 共用——GOOSE_CONTEXT_LIMIT；缺＝goose 自行推算）
            if context_length is not None:
                goose_env["GOOSE_CONTEXT_LIMIT"] = str(context_length)

            # 自訂 provider 檔（goose 原生唔識嘅 provider——寫入 custom_providers 讀取位；原生／唔支持＝略過）
            self._write_custom_provider_file(goose_env["GOOSE_PATH_ROOT"], model_name, context_length)

            # chat_mode 對映查表（UI 三模 → goose 原生模式；空＝引擎自身默認）
            goose_mode = self._resolve_goose_mode()

            # 判定：有對映（非空）→ 落子程序環境（CLI／ACP 共用——GOOSE_MODE）
            if goose_mode != "":
                goose_env["GOOSE_MODE"] = goose_mode

            # 傳輸判定（runtime.json daemon_transport——acp＝協定互動路徑；cli＝現行）
            daemon_transport = self._resolve_daemon_transport()

            # 判定：ACP 傳輸 → 走 ACP 路徑（權限請求可互動——其餘契約同源）
            if daemon_transport == "acp":
                self._run_job_acp(goose_env, goose_executable, goose_session_name, content, project_path, msg_stdout_file, goose_mode)
                return

            # spawn（--resume 與否＝session 存在判定；stderr 併入 stdout——行行都係紀錄）
            goose_proc = self._spawn_goose(goose_executable, goose_env, goose_session_name, content, project_path)

            # 啟動完成（執行檔已備＋子程序已起＋管線已接——尚未查詢）——自報「就緒」
            msg_log().append_info_log(EVENT_SUBPROCESS_READY, "Subprocess ready")
            msg_state_machine().apply_and_report(EVENT_SUBPROCESS_READY)

            # 串流翻譯（阻塞至子程序結束；取消由本迴圈兜底）
            self._pump_stream(goose_proc, msg_stdout_file)

            # 子程序結束——非零 exit 即失敗（成功與否終態由父程序收尾判定）
            goose_exit_code = goose_proc.wait()

            # 判定：非零 exit → 記錯誤（串流中斷／啟動失敗）
            if goose_exit_code != 0:
                msg_log().append_error_log(
                    EVENT_FAILURE_RECORDED,
                    "goose exited with code " + str(goose_exit_code),
                    {"exit_code": goose_exit_code},
                )
                msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)

        # 盲捕例外（刻意——單次 run 失敗要完整收尾，不能外洩）
        except Exception as exc:  # noqa: BLE001
            # 例外訊息寫入 msg_log（ERROR 事件——短句進 msg_log、完整內容進 error_log）
            msg_log().append_error_log(
                EVENT_FAILURE_RECORDED,
                type(exc).__name__,
                {
                    "exception_type": type(exc).__name__,
                    "exception_message": str(exc),
                },
                detail=str(exc),
            )
            sc_logger.log("error chat_id=" + str(chat_id) + ": " + str(exc))   # sc_logger.log 內部盲捕——已安全
            msg_state_machine().apply_and_report(EVENT_FAILURE_RECORDED)

        finally:
            # 子程序殘存（例外路徑——正常已自然結束）→ 樹狀安全終止
            self._terminate_goose(goose_proc)

    # ----------------------------------------------------------
    # goose 環境／子程序
    # ----------------------------------------------------------

    def _build_goose_env(self, model_name: str) -> dict | None:
        """
        由 model_name（litellm 式 provider/model）組 goose 子程序環境（回傳＝完整 env；唔支持＝None）。

        支持：openrouter/<model>（goose 原生 openrouter provider——key＝OPENROUTER_API_KEY）；
        自訂 provider 表前綴（如 kilo/<model>——goose 經 custom_providers/<前綴>.json 接駁）；
        兩路 key 皆由 oauth keys 檔載入本程序環境，子程序繼承。
        """
        # provider 前綴（第一個 "/" 前）＋模型名（後——可再含 "/"，如 openrouter/deepseek/deepseek-v4-flash）
        provider_prefix, separator, model_part = model_name.partition("/")

        # 判定：唔支持嘅 provider（openrouter 原生／自訂表以外）→ None
        if provider_prefix not in ["openrouter", *CUSTOM_PROVIDER_TABLE]:
            return None

        # 判定：模型名缺失 → None
        if model_part == "":
            return None

        goose_env = dict(os.environ)
        goose_env["GOOSE_PROVIDER"] = provider_prefix
        goose_env["GOOSE_MODEL"]    = model_part

        return goose_env

    def _resolve_api_key_env_name(self, model_name: str) -> str:
        """model_name 前綴 → 對應 API key 環境變數名（自訂表＝表內 api_key_env；原生＝OPENROUTER_API_KEY）。"""
        provider_prefix = model_name.partition("/")[0]

        # 判定：自訂表內 → 用表內 key 名
        if provider_prefix in CUSTOM_PROVIDER_TABLE:
            return str(CUSTOM_PROVIDER_TABLE[provider_prefix]["api_key_env"])

        # 原生（openrouter——現階段唯一原生 provider）
        return "OPENROUTER_API_KEY"

    def _resolve_goose_executable(self) -> str:
        """goose 執行檔：runtime.json external_paths.goose_executable 優先；缺＝PATH 搜尋；再缺＝空字串。"""
        goose_executable = str(daemon().get_daemon_conf_data().get("goose_executable", "") or "")

        # 判定：配置有值 → 用之
        if goose_executable != "":
            return goose_executable

        # 退回 PATH 搜尋（shutil.which——跨平台）
        return shutil.which("goose") or ""

    def _resolve_goose_path_root(self, project_path: str) -> str:
        """goose 資料根（GOOSE_PATH_ROOT——每專案一個資料夾；session／config／state 全歸該專案）。"""
        storage_dir  = str(daemon().get_daemon_conf_data().get("storage_dir", ""))
        project_slug = msg_main().get_project_slug()

        # 判定：無 slug → 退資料夾名（舊資料／未帶 slug）
        if project_slug == "":
            project_slug = Path(project_path).name

        return str(Path(storage_dir) / "localdb" / "goose" / project_slug)

    def _write_goose_hints_file(self, goose_path_root: str) -> None:
        """
        寫 goose Global Hints 檔（<GOOSE_PATH_ROOT>/config/.goosehints——goose 原生 context 機制；
        載入點＝goose 核心 load_hints〔config 目錄〕——每專案 GOOSE_PATH_ROOT 天然隔離）。
        內容＝hkai 三源（system_prompt＋system_env＋project env；項間空行分隔）——每問一次刷新，內容無變唔重寫。
        缺必要檔（system_prompt／system_env 或其設定）＝RuntimeError（唔准靜默——呼叫端記失敗收工）。
        """
        daemon_conf_data  = daemon().get_daemon_conf_data()
        storage_dir       = Path(str(daemon_conf_data.get("storage_dir", "")))
        instructions_conf = daemon_conf_data.get("instructions", {})

        # 系統提示全文（root 檔——永遠取來源）
        system_prompt_relative = str(instructions_conf.get("system_prompt", {}).get("path", ""))

        # 判定：設定缺路徑 → 爆（唔准靜默）
        if system_prompt_relative == "":
            raise RuntimeError("system instructions config missing: instructions.system_prompt.path")

        system_prompt_path = storage_dir / system_prompt_relative

        # 判定：檔唔喺度 → 爆（唔准靜默）
        if system_prompt_path.is_file() is False:
            raise RuntimeError("system instructions unavailable: " + str(system_prompt_path))

        # 系統環境（root 檔——同一模式）
        system_env_relative = str(instructions_conf.get("system_env", {}).get("path", ""))

        # 判定：設定缺路徑 → 爆
        if system_env_relative == "":
            raise RuntimeError("system instructions config missing: instructions.system_env.path")

        system_env_path = storage_dir / system_env_relative

        # 判定：檔唔喺度 → 爆
        if system_env_path.is_file() is False:
            raise RuntimeError("system instructions unavailable: " + str(system_env_path))

        hints_sections = [
            system_prompt_path.read_text(encoding="utf-8"),
            system_env_path.read_text(encoding="utf-8"),
        ]

        # 專案環境（後備源——偵測失敗可缺；缺＝略過記行）
        project_env_path = storage_dir / "environment" / ("env." + msg_main().get_project_slug() + ".md")

        # 判定：檔喺度 → 帶
        if project_env_path.is_file() is True:
            hints_sections.append(project_env_path.read_text(encoding="utf-8"))
        else:
            sc_logger.log("project environment file missing: " + str(project_env_path))

        hints_text = "\n\n".join(hints_sections)

        # 寫檔（內容無變＝唔動——免無謂刷新；原子寫：.tmp → replace）
        hints_file = Path(goose_path_root) / "config" / ".goosehints"

        # 判定：舊檔內容一樣 → 直返
        if hints_file.is_file() is True:
            existing_text = hints_file.read_text(encoding="utf-8")
            if existing_text == hints_text:
                return

        hints_file.parent.mkdir(parents=True, exist_ok=True)
        temporary_file = hints_file.with_name(hints_file.name + ".tmp")
        temporary_file.write_text(hints_text, encoding="utf-8")
        os.replace(temporary_file, hints_file)
        sc_logger.log("goose hints written: " + str(hints_file) + " (" + str(len(hints_text)) + " chars)")

    def _write_custom_provider_file(self, goose_path_root: str, model_name: str, context_length: int | None) -> None:
        """
        寫 goose 自訂 provider 檔（<GOOSE_PATH_ROOT>/config/custom_providers/<前綴>.json——goose 原生讀取位；
        只限自訂表內嘅 provider 前綴，原生／唔支持＝略過）。
        內容＝goose 官方 schema（engine＝openai 相容；models 只列當題模型；key 只寫環境變數名——值唔入檔）。
        每問一次刷新，內容無變唔重寫（原子寫：.tmp → replace）。
        """
        provider_prefix, separator, model_part = model_name.partition("/")

        # 判定：唔喺自訂表（原生／唔支持）→ 唔使寫
        if provider_prefix not in CUSTOM_PROVIDER_TABLE:
            return

        provider_conf = CUSTOM_PROVIDER_TABLE[provider_prefix]

        # 當題模型項（name＋context_limit——有值先落）
        model_item: dict = {"name": model_part}

        # 判定：msg_conf 有帶 context_length → 落 context_limit（缺＝goose 自行推算）
        if context_length is not None:
            model_item["context_limit"] = context_length

        # goose 官方 schema（engine＝openai 相容——自訂 provider 統一型）
        provider_file_data = {
            "name":               provider_prefix,
            "engine":             "openai",
            "display_name":       provider_conf["display_name"],
            "description":        provider_conf["description"],
            "api_key_env":        provider_conf["api_key_env"],
            "base_url":           provider_conf["base_url"],
            "models":             [model_item],
            "supports_streaming": True,
            "requires_auth":      True,
        }

        provider_file_text = json.dumps(provider_file_data, indent=2, ensure_ascii=False) + "\n"

        # 寫檔（內容無變＝唔動——免無謂刷新；原子寫：.tmp → replace）
        provider_file_path = Path(goose_path_root) / "config" / "custom_providers" / (provider_prefix + ".json")

        # 判定：舊檔內容一樣 → 直返
        if provider_file_path.is_file() is True:
            existing_text = provider_file_path.read_text(encoding="utf-8")
            if existing_text == provider_file_text:
                return

        provider_file_path.parent.mkdir(parents=True, exist_ok=True)
        temporary_file = provider_file_path.with_name(provider_file_path.name + ".tmp")
        temporary_file.write_text(provider_file_text, encoding="utf-8")
        os.replace(temporary_file, provider_file_path)
        sc_logger.log("goose custom provider written: " + str(provider_file_path))

    def _goose_session_exists(self, goose_executable: str, goose_env: dict, goose_session_name: str) -> bool:
        """查 goose session 清單（session list）——存在同名 → True（查詢失敗一律 False＝建新）。"""
        subprocess_kwargs = {}

        # 判定：Windows → 無視窗（console 程式會被開黑窗）
        if sys.platform == "win32":
            subprocess_kwargs["creationflags"] = subprocess.CREATE_NO_WINDOW

        try:
            probe = subprocess.run(
                [goose_executable, "session", "list"],
                env=goose_env,
                stdin=subprocess.DEVNULL,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=30,
                **subprocess_kwargs,
            )
        except Exception:  # noqa: BLE001
            return False

        # 清單行格式：{id} - {name} - {time} - {cwd}——以第二欄（名）核對
        for session_line in probe.stdout.splitlines():
            session_fields = [session_field.strip() for session_field in session_line.split(" - ")]

            # 判定：第二欄＝本對話 session 名 → 存在
            if len(session_fields) >= 2 and session_fields[1] == goose_session_name:
                return True

        return False

    def _spawn_goose(self, goose_executable: str, goose_env: dict, goose_session_name: str, content: str, project_path: str):
        """
        spawn goose run 子程序（stream-json——逐事件 JSON 行）。

        session 續話：清單已存在同名 → --resume 續；唔存在 → 建立（-n）。
        """
        command = [
            goose_executable,
            "run",
            "--name", goose_session_name,
            "--text", content,
            "--output-format", "stream-json",
        ]

        # 判定：session 已存在 → 加 --resume（續會話；唔存在＝建立新）
        if self._goose_session_exists(goose_executable, goose_env, goose_session_name):
            command.insert(2, "--resume")

        subprocess_kwargs = {}

        # 判定：Windows → 無視窗（console 程式會被開黑窗）
        if sys.platform == "win32":
            subprocess_kwargs["creationflags"] = subprocess.CREATE_NO_WINDOW

        # stderr 併入 stdout（同一管線——行行都係紀錄）；bufsize=1（行緩衝）
        return subprocess.Popen(
            command,
            cwd=project_path,
            env=goose_env,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            encoding="utf-8",
            errors="replace",
            bufsize=1,
            **subprocess_kwargs,
        )

    # ----------------------------------------------------------
    # ACP 路徑（goose acp——stdio 協定；協定細節喺 engine/acp_client.py）
    # ----------------------------------------------------------

    def _resolve_daemon_transport(self) -> str:
        """傳輸模式（runtime.json daemon_transport——acp／cli；缺／非法＝acp）。"""
        daemon_transport = str(daemon().get_daemon_conf_data().get("daemon_transport", "") or "")

        # 判定：非合法值（acp／cli）→ 退回 acp（現行路徑——協定通道）
        if daemon_transport not in ("acp", "cli"):
            return "acp"

        return daemon_transport

    def _resolve_goose_mode(self) -> str:
        """
        chat_mode 對映查表（本張工單 msg_conf.chat_mode → runtime.json daemon_chat_mode_map）。

        回傳＝goose 原生模式（GOOSE_MODE／session mode 用）；缺／未對映＝空字串（引擎自身默認接手——記一行）。
        """
        msg_conf_data = msg_main().get_msg_conf_data()
        chat_mode     = str(msg_conf_data.get("chat_mode", ""))
        chat_mode_map = daemon().get_daemon_conf_data().get("daemon_chat_mode_map", {})
        goose_mode    = str(chat_mode_map.get(chat_mode, ""))

        # 判定：未對映（缺／詞彙唔夾）→ 記行（引擎自身默認接手）
        if goose_mode == "":
            sc_logger.log("chat mode not mapped for goose: '" + chat_mode + "'")

        return goose_mode

    def _run_job_acp(self, goose_env: dict, goose_executable: str, goose_session_name: str, content: str, project_path: str, msg_stdout_file: Path, goose_mode: str) -> None:
        """
        goose ACP 路徑：組本次連線參數（同名續話開；session 名＝每對話一條）→ 交共同 ACP 路徑。
        權限檔案契約（message 目錄）：
            msg_permission.json           本側寫（pending 請求——PHP 讀出提示卡）
            msg_permission_decision.json  本側讀（使用者決定；逾時＝本側寫入 deny）
            msg_conf.json                 本側讀（permission_allow_all／permission_deny_all 旗標；PHP 寫——本輪免問）
        """
        # 本次連線參數（dict——key 同名變數喺 acp_client 內解包）
        session_request = {
            "agent_command":   [goose_executable, "acp"],
            "agent_env":       goose_env,
            "project_path":    project_path,
            "session_title":   goose_session_name,
            "prompt_text":     content,
            "session_mode":    goose_mode,
            "resume_session":  True,
            "session_history": None,
        }

        # 交 ACP 路徑（回呼／串流語義）
        self._run_acp_session(session_request, goose_session_name, msg_stdout_file)

    def _run_acp_session(self, session_request: dict, session_label: str, msg_stdout_file: Path) -> None:
        """
        ACP 路徑（goose acp 專用）：串流狀態＋回呼組裝
        （輸出契約／權限卡語義）→ 交協定客戶端跑（同步阻塞——回呼驅動一切）。
        """
        message_id = msg_main().get_message_id()

        # 串流翻譯狀態（跨回呼共享——同 CLI _pump_stream 局部變數語義）
        stream_state = {
            "response_index":       0,
            "current_message_id":   "",
            "first_content_seen":   False,
            "stream_opened_logged": False,
            "usage":                {},
        }

        # 回呼（acp_client 事件 → 本側契約——逐支見各方法）
        callbacks = {
            "on_stdio_line":         lambda stdio_line: self._append_msg_stdout(msg_stdout_file, stdio_line),
            "on_ready":              lambda session_id: self._acp_ready(session_label),
            "on_update":             lambda update_kind, update_payload: self._acp_update(update_kind, update_payload, stream_state),
            "on_permission_request": lambda tool_call: self._acp_permission_request(tool_call, message_id),
            "on_prompt_finished":    lambda prompt_response: self._acp_prompt_finished(prompt_response, stream_state),
            "on_cancelled":          lambda: self._acp_cancelled(),
            "is_cancelled":          lambda: msg_main().get_msg_status() == "cancelled",
        }

        # 交協定客戶端跑（同步阻塞——回呼驅動一切）
        acp_client().run_session(session_request, callbacks)

    def _acp_ready(self, session_label: str) -> None:
        """ACP 就緒（連線＋session＋模式已備——未查詢）——同 CLI「就緒」同一事件語義。"""
        msg_log().append_info_log(EVENT_SUBPROCESS_READY, "ACP session ready: " + session_label)
        msg_state_machine().apply_and_report(EVENT_SUBPROCESS_READY)

    def _acp_update(self, update_kind: str, update_payload: dict, stream_state: dict) -> None:
        """
        ACP 事件 → 契約輸出（對位 CLI _pump_stream 翻譯）：
            thought → {i}.thinking 檔；text → {i}.result 檔（首段記串流開啟）；replay → {i}.replay 檔（load 重播——獨立歸檔，唔屬本輪回應）；
            tool → TOOL_INVOKED 事件；usage → 暫存（本輪完結時記）；other → 免記（協定追蹤已留痕）。
        段落 index：_meta.messageId 換新（上一輪已有內容）即前進——同 CLI message id 語義。
        """
        # 引擎重播段（load 重播——唔屬本輪回應；獨立歸檔；唔觸發連通／段 index 前進）
        if update_kind == "replay":
            msg_log().append_response_replay(stream_state["response_index"], update_payload.get("text", ""))
            return

        message_id = update_payload.get("message_id", "")

        # 首個內容（thought／text）→ 連通（replying——只發一次）
        is_content_update = update_kind in ("thought", "text")
        if is_content_update and stream_state["first_content_seen"] is False:
            stream_state["first_content_seen"] = True
            msg_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "goose output arrived")
            msg_state_machine().apply_and_report(EVENT_CONNECTION_ESTABLISHED)

        # 判定：新 message id（上一輪已有內容）→ 段落 index 前進（每輪一對檔）
        if message_id != "" and stream_state["current_message_id"] != "" and message_id != stream_state["current_message_id"]:
            stream_state["response_index"] += 1

        # 判定：本輪 message id → 記為當前
        if message_id != "":
            stream_state["current_message_id"] = message_id

        # 思考段（reasoning——入 {i}.thinking 檔）
        if update_kind == "thought":
            msg_log().append_response_thinking(stream_state["response_index"], update_payload.get("text", ""))
            return

        # 主回答段（入 {i}.result 檔；首段記串流開啟）
        if update_kind == "text":
            # 判定：尚未記串流開啟 → 記一次（trace）
            if stream_state["stream_opened_logged"] is False:
                stream_state["stream_opened_logged"] = True
                msg_log().append_info_log(EVENT_REPLY_STREAM_OPENED, "goose reply stream opened")
            msg_log().append_response_result(stream_state["response_index"], update_payload.get("text", ""))
            return

        # 工具活動（TOOL_INVOKED 事件——trace）
        if update_kind == "tool":
            msg_log().append_info_log(
                EVENT_TOOL_INVOKED,
                "ACP tool: " + update_payload.get("title", ""),
                {"tool_call_id": update_payload.get("tool_call_id", "")},
            )
            return

        # 用量更新（暫存——本輪完結時同完成事件一齊記）
        if update_kind == "usage":
            stream_state["usage"] = update_payload

    async def _acp_permission_request(self, tool_call, message_id: str) -> str:
        """
        ACP 權限請求（async——acp_client await 本支）：寫 pending 請求 → 等決定
        （上限 PERMISSION_WAIT_TIMEOUT_SEC 秒、每 PERMISSION_DECISION_POLL_INTERVAL_SEC 秒查一次）
        → 回選項 id（allow_once／reject_once）；逾時＝本側代寫 deny 決定。
        本輪已「允許全部」（msg_conf 旗標）＝免問：唔寫 pending、直接回 allow_once。
        本輪已「拒絕全部」（msg_conf 旗標）＝免問：唔寫 pending、直接回 reject_once。
        """
        permission_request_file  = msg_main().get_msg_file_path("permission", message_id)
        permission_decision_file = msg_main().get_msg_file_path("permission_decision", message_id)

        request_id = str(getattr(tool_call, "tool_call_id", "") or "")
        tool_title = str(getattr(tool_call, "title", "") or "")
        tool_input = getattr(tool_call, "raw_input", None)

        # 判定：本輪已允許全部（msg_conf 旗標）→ 直接放行（唔出卡）
        if self._is_permission_allow_all(message_id):
            msg_log().append_info_log(
                EVENT_TOOL_INVOKED,
                "Permission auto-allowed (allow all for this message)",
                {"request_id": request_id, "tool_title": tool_title},
            )
            return "allow_once"

        # 判定：本輪已拒絕全部（msg_conf 旗標）→ 直接拒絕（唔出卡）
        if self._is_permission_deny_all(message_id):
            msg_log().append_info_log(
                EVENT_TOOL_INVOKED,
                "Permission auto-denied (deny all for this message)",
                {"request_id": request_id, "tool_title": tool_title},
            )
            return "reject_once"

        # 上一請求殘留先清（request_id 核對係第二重保險——PHP 側亦核）
        self._remove_file_if_exists(permission_decision_file)

        # 寫 pending 請求（原子——PHP 讀到嘅永遠係完整 JSON）
        deadline_ts = int(time.time()) + PERMISSION_WAIT_TIMEOUT_SEC
        permission_request = {
            "request_id":  request_id,
            "tool_title":  tool_title,
            "tool_input":  tool_input if isinstance(tool_input, (dict, list)) else str(tool_input or ""),
            "created_at":  int(time.time()),
            "deadline_ts": deadline_ts,
        }
        self._write_json_atomic(permission_request_file, permission_request)
        msg_log().append_info_log(
            EVENT_TOOL_INVOKED,
            "Permission requested: " + tool_title,
            {"request_id": request_id, "tool_title": tool_title},
        )

        # 等決定（每 tick 查一次決定檔；逾時＝自動 deny）
        while time.time() < deadline_ts:
            # 判定：使用者已取消 → 當拒絕收（watchdog 同時會終止連線）
            if msg_main().get_msg_status() == "cancelled":
                msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Permission wait cancelled by user")
                return "reject_once"

            decision = self._read_permission_decision(permission_decision_file, request_id)

            # 判定：使用者允許 → 回允許
            if decision == "allow":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed by user", {"request_id": request_id})
                return "allow_once"

            # 判定：使用者允許全部（本輪）→ 照放行（後續請求靠 msg_conf 旗標——唔再出卡）
            if decision == "allow_all":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission allowed for this message (allow all)", {"request_id": request_id})
                return "allow_once"

            # 判定：使用者拒絕全部（本輪）→ 照拒（後續請求靠 msg_conf 旗標——唔再出卡）
            if decision == "deny_all":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied for this message (deny all)", {"request_id": request_id})
                return "reject_once"

            # 判定：使用者拒絕 → 回拒絕
            if decision == "deny":
                msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission denied by user", {"request_id": request_id})
                return "reject_once"

            await asyncio.sleep(PERMISSION_DECISION_POLL_INTERVAL_SEC)

        # 逾時（無人決定）→ 本側代寫 deny（紀錄一體）＋回拒絕
        timeout_decision = {
            "request_id": request_id,
            "decision":   "deny",
            "source":     "timeout",
            "decided_at": int(time.time()),
        }
        self._write_json_atomic(permission_decision_file, timeout_decision)
        msg_log().append_info_log(EVENT_TOOL_INVOKED, "Permission timed out (auto deny)", {"request_id": request_id})

        return "reject_once"

    def _read_permission_decision(self, permission_decision_file: Path, request_id: str) -> str:
        """讀決定檔（request_id 對唔上＝上一請求殘留 → 當未決；缺／壞 → 空字串）。"""
        try:
            decision_data = json.loads(permission_decision_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return ""

        # 判定：非 dict → 未決
        if not isinstance(decision_data, dict):
            return ""

        # 判定：request_id 唔對（殘留）→ 未決
        if str(decision_data.get("request_id", "")) != request_id:
            return ""

        return str(decision_data.get("decision", ""))

    def _is_permission_allow_all(self, message_id: str) -> bool:
        """本輪已允許全部？（讀 msg_conf 旗標 permission_allow_all——每次問之前讀硬檔；缺／壞 → False）。"""
        permission_conf_file = msg_main().get_msg_file_path("conf", message_id)

        try:
            conf_data = json.loads(permission_conf_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return False

        # 判定：非 dict → 未允許
        if not isinstance(conf_data, dict):
            return False

        return bool(conf_data.get("permission_allow_all", False))

    def _is_permission_deny_all(self, message_id: str) -> bool:
        """本輪已拒絕全部？（讀 msg_conf 旗標 permission_deny_all——每次問之前讀硬檔；缺／壞 → False）。"""
        permission_conf_file = msg_main().get_msg_file_path("conf", message_id)

        try:
            conf_data = json.loads(permission_conf_file.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            return False

        # 判定：非 dict → 未拒絕
        if not isinstance(conf_data, dict):
            return False

        return bool(conf_data.get("permission_deny_all", False))

    def _write_json_atomic(self, target_file: Path, json_payload: dict) -> None:
        """原子寫 JSON（tmp＋replace——讀者唔會見到半截檔）。"""
        temporary_file = target_file.with_suffix(target_file.suffix + ".tmp")
        temporary_file.write_text(json.dumps(json_payload, ensure_ascii=False), encoding="utf-8")
        os.replace(temporary_file, target_file)

    def _remove_file_if_exists(self, target_file: Path) -> None:
        """檔案存在即刪（殘留清理——唔存在＝正常）。"""
        try:
            target_file.unlink()
        except FileNotFoundError:
            pass

    def _acp_prompt_finished(self, prompt_response, stream_state: dict) -> None:
        """
        本輪結束（PromptResponse）——同 CLI complete 分支語義：
        用量記 RESPONSE_COMPLETED（成功事件）；stop_reason＝cancelled 除外（取消路徑唔當完成）。
        """
        stop_reason = str(getattr(prompt_response, "stop_reason", "") or "")

        # 判定：協定報 cancelled（本側曾發取消）→ 唔記完成（收尾交取消路徑）
        if stop_reason == "cancelled":
            msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "ACP turn cancelled")
            return

        usage = stream_state.get("usage", {})
        usage_metadata = {
            "total_tokens":  usage.get("used", 0),
            "context_size":  usage.get("size", 0),
            "cost_usd":      usage.get("cost_usd", 0),
        }
        msg_log().append_success_log(EVENT_RESPONSE_COMPLETED, "ACP stream completed", usage_metadata)
        msg_state_machine().apply_and_report(EVENT_RESPONSE_COMPLETED)

    def _acp_cancelled(self) -> None:
        """使用者取消（watchdog 測到）——記事件一次（終止緊隨其後）。"""
        msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — stopping goose (ACP)")

    # ----------------------------------------------------------
    # 串流翻譯（goose stream-json 行 → 契約輸出）
    # ----------------------------------------------------------

    def _pump_stream(self, goose_proc, msg_stdout_file: Path) -> None:
        """
        逐行消費 goose stdout：raw 行全寫 msg_stdout（原樣）；JSON 行翻譯成
        response 段檔＋FSM 事件；取消（msg_status=cancelled）即終止子程序。
        """
        # 讀取執行緒：pipe 行 → queue（主迴圈同時間隙可查取消——Windows 管道無 select）
        line_queue: "queue.Queue" = queue.Queue()
        reader_thread = threading.Thread(
            target=self._read_stdout_lines,
            args=(goose_proc, line_queue),
            daemon=True,
        )
        reader_thread.start()

        response_index = 0
        current_message_id = ""
        first_content_seen = False
        stream_opened_logged = False

        while True:
            try:
                raw_line = line_queue.get(timeout=1.0)
            except queue.Empty:
                # 判定：使用者已取消 → 終止子程序（繼續讀殘行）
                if msg_main().get_msg_status() == "cancelled":
                    msg_log().append_info_log(EVENT_CANCELLATION_REQUESTED, "Cancelled by user — stopping goose")
                    self._terminate_goose(goose_proc)

                # 判定：程序已停＋行已讀盡 → 收工
                if goose_proc.poll() is not None and line_queue.empty():
                    break
                continue

            # 哨兵（None）＝讀取執行緒已見 EOF → 收工
            if raw_line is None:
                break

            # raw 原樣寫入 msg_stdout（每次 append——同 stdout_writer 語義）
            self._append_msg_stdout(msg_stdout_file, raw_line)

            # 解析 JSON 行（banner 等非 JSON → 已入 raw，略過）
            record = self._parse_goose_json_line(raw_line)

            # 判定：非 JSON → 已入 raw，略過
            if record is None:
                continue

            record_type = str(record.get("type", ""))

            # type message：assistant 內容分流（thinking／text；首內容即連通）
            if record_type == "message":
                message_obj = record.get("message", {})
                message_id  = str(message_obj.get("id", ""))
                content_items = message_obj.get("content", [])

                # 首個內容 → 連通（replying——只發一次）
                if first_content_seen is False and len(content_items) > 0:
                    first_content_seen = True
                    msg_log().append_info_log(EVENT_CONNECTION_ESTABLISHED, "goose output arrived")
                    msg_state_machine().apply_and_report(EVENT_CONNECTION_ESTABLISHED)

                # 判定：新 message id（上一輪已有內容）→ 段落 index 前進（每輪一對檔）
                if message_id != "" and current_message_id != "" and message_id != current_message_id:
                    response_index += 1

                # 判定：本輪 message id → 記為當前
                if message_id != "":
                    current_message_id = message_id

                # 內容逐項分流
                for content_item in content_items:
                    content_type = str(content_item.get("type", ""))

                    # thinking 段（reasoning——入 {response_index}.thinking 檔）
                    if content_type == "thinking":
                        thinking_text = str(content_item.get("thinking", ""))
                        msg_log().append_response_thinking(response_index, thinking_text)
                        continue

                    # text 段（主回答——入 {response_index}.result 檔；首段記串流開啟）
                    if content_type == "text":
                        # 判定：尚未記串流開啟 → 記一次（trace）
                        if stream_opened_logged is False:
                            stream_opened_logged = True
                            msg_log().append_info_log(EVENT_REPLY_STREAM_OPENED, "goose reply stream opened")
                        text_piece = str(content_item.get("text", ""))
                        msg_log().append_response_result(response_index, text_piece)
                        continue

                    # 其餘（tool_use 等）→ 工具痕跡（raw 已留——此處記事件）
                    msg_log().append_info_log(
                        EVENT_TOOL_INVOKED,
                        "goose content: " + content_type,
                        {"content_item": content_item},
                    )
                continue

            # type complete：串流收尾（usage／cost 統計——成功事件）
            if record_type == "complete":
                usage_metadata = {
                    "total_tokens":  record.get("total_tokens", 0),
                    "input_tokens":  record.get("input_tokens", 0),
                    "output_tokens": record.get("output_tokens", 0),
                    "cost_usd":      record.get("cost_usd", 0),
                }
                msg_log().append_success_log(EVENT_RESPONSE_COMPLETED, "goose stream completed", usage_metadata)
                msg_state_machine().apply_and_report(EVENT_RESPONSE_COMPLETED)
                continue

            # 其餘 record type → 略過（raw 已留痕）

    def _read_stdout_lines(self, goose_proc, line_queue: "queue.Queue") -> None:
        """背景讀取執行緒：逐行讀 goose stdout → 入隊；EOF 後送哨兵（None）。"""
        try:
            for raw_line in goose_proc.stdout:
                line_queue.put(raw_line.rstrip("\r\n"))
        except Exception:  # noqa: BLE001
            pass
        finally:
            line_queue.put(None)

    def _append_msg_stdout(self, msg_stdout_file: Path, raw_line: str) -> None:
        """raw 行 append 寫入 msg_stdout（原樣——首寫即建立檔）。"""
        with msg_stdout_file.open("a", encoding="utf-8", errors="replace") as output_file:
            output_file.write(raw_line + "\n")

    def _parse_goose_json_line(self, raw_line: str) -> dict | None:
        """解析 goose stream-json 行；非 JSON（banner／統計字串）→ None。"""
        stripped_line = raw_line.strip()

        # 判定：空行或非 { 開頭 → 非 JSON
        if stripped_line == "" or not stripped_line.startswith("{"):
            return None

        try:
            record = json.loads(stripped_line)
        except Exception:  # noqa: BLE001
            return None

        # 判定：非 dict → None
        if not isinstance(record, dict):
            return None

        return record

    def _terminate_goose(self, goose_proc) -> None:
        """安全終止 goose 子程序（含其子孫——先子後母：terminate → 逾時 kill）。"""
        # 判定：無把手或已結束 → 直返
        if goose_proc is None or goose_proc.poll() is not None:
            return

        # 子孫清單（recursive——goose 工具呼叫可能開咗子程序）
        try:
            descendants = psutil.Process(goose_proc.pid).children(recursive=True)
        except psutil.Error:
            descendants = []

        # 先客氣（子孫＋本體 terminate）
        for child_process in descendants:
            try:
                child_process.terminate()
            except psutil.Error:
                pass
        goose_proc.terminate()

        # 逾時未死 → 硬殺（子孫＋本體）
        try:
            goose_proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            for child_process in descendants:
                try:
                    child_process.kill()
                except psutil.Error:
                    pass
            goose_proc.kill()
            goose_proc.wait()

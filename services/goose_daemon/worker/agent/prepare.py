#!/usr/bin/env python3
"""
prepare.py

agent啟動前準備（單一職責：每次發問前備妥 goose 子程序環境與設定檔）。

上游：entry.execute_job（每張工單呼叫 prepare_goose_env 一次）
下游：paths（路徑／自訂 provider 表）、chat_mode（UI 模對映）、
      message_store（msg_conf 值）、runtime/sc_config（daemon 設定）、bootstrap/sc_logger

產出：
    goose_env（GOOSE_PATH_ROOT／GOOSE_CONTEXT_LIMIT／GOOSE_MODE）
    <GOOSE_PATH_ROOT>/config/.goosehints（系統指示）
    <GOOSE_PATH_ROOT>/config/custom_providers/<前綴>.json（自訂 provider）
"""

import json
import os
import sys
from pathlib import Path

from ...context import DATA_DIR, message_store
from bootstrap import sc_logger
from . import paths
from .chat_mode import get_chat_mode

# 系統指示材料相對名（出廠固定——不允許用戶改，故不經 hkai_config.json）
SYSTEM_PROMPT_RELATIVE = "instructions/system_prompt.md"
SYSTEM_ENV_RELATIVE = "instructions/system_env.md"


def prepare_goose_env(goose_env: dict[str, str]) -> None:
    """備妥 goose 子程序環境（GOOSE_PATH_ROOT／hints／上下文上限／自訂 provider／GOOSE_MODE——原地改 goose_env）。"""
    # 語境（msg_conf——execute_job 已載入 message_store）
    project_path = message_store().get_project_path()
    model_name   = message_store().get_model_name()

    # goose 資料根（每專案一個資料夾——session／config／state 全歸該專案；專案間零干涉）
    goose_env["GOOSE_PATH_ROOT"] = str(paths.resolve_path_root(project_path))

    # 內建 node：加入子程序 PATH（npx／node 用內建版；冇就用系統）
    node_dir_text = paths.resolve_node_dir()

    if node_dir_text != "":
        goose_env["PATH"] = node_dir_text + os.pathsep + goose_env.get("PATH", "")

    # 內建 Playwright：瀏覽器路徑（goose 開瀏覽器時用）
    playwright_dir_text = paths.resolve_playwright_dir()

    if playwright_dir_text != "":
        goose_env["PLAYWRIGHT_BROWSERS_PATH"] = playwright_dir_text + "/browsers"

    # 系統指示（hkai——發問前注入：寫 goose 原生 Global Hints 檔；缺必要檔＝掟錯，外層記失敗）
    write_goose_hints_file(goose_env["GOOSE_PATH_ROOT"])

    # 上下文長度（msg_conf——model store 數字；goose auto compact 以此計閾值）
    context_length = message_store().get_context_length()

    # 判定：有值 → 落 ACP 子程序環境（GOOSE_CONTEXT_LIMIT；缺＝goose 自行推算）
    if context_length is not None:
        goose_env["GOOSE_CONTEXT_LIMIT"] = str(context_length)

    # 自訂 provider 檔（goose 原生唔識嘅 provider——寫入 custom_providers 讀取位；原生／唔支持＝略過）
    write_custom_provider_file(goose_env["GOOSE_PATH_ROOT"], model_name, context_length)

    # chat_mode 對映（UI 模 → goose 原生模式；未對映＝auto）
    chat_mode = message_store().get_chat_mode()
    chat_mode = get_chat_mode(chat_mode)

    # 落 ACP 子程序環境（GOOSE_MODE——get_chat_mode 必有值，未對映＝auto）
    goose_env["GOOSE_MODE"] = chat_mode

    # Windows：cmd 出口強制 UTF-8（ComSpec 指向包裝 bat——chcp 65001 再交真 cmd）
    if sys.platform == "win32":
        cmd_wrapper = Path(__file__).resolve().parents[2] / "tools" / "cmd_utf8.bat"

        # 判定：包裝檔存在 → 換 ComSpec（保留原本）
        if cmd_wrapper.is_file() is True:
            _ = goose_env.setdefault("SC_REAL_COMSPEC", goose_env.get("ComSpec", os.environ.get("ComSpec", "")))
            goose_env["ComSpec"] = str(cmd_wrapper)

        goose_env["PYTHONUTF8"] = "1"
        goose_env["PYTHONIOENCODING"] = "utf-8"


def write_goose_hints_file(goose_path_root: str) -> None:
    """
    寫 goose Global Hints 檔（<GOOSE_PATH_ROOT>/config/.goosehints——goose 原生 context 機制；
    載入點＝goose 核心 load_hints〔config 目錄〕——每專案 GOOSE_PATH_ROOT 天然隔離）。
    內容＝hkai 三源（system_prompt＋system_env＋project env；項間空行分隔）——每問一次刷新，內容無變唔重寫。
    缺必要檔（system_prompt／system_env）＝RuntimeError（唔准靜默——呼叫端記失敗收工）。
    """
    # 系統提示全文（root 檔——永遠取來源；相對名出廠固定）
    system_prompt_path = DATA_DIR / SYSTEM_PROMPT_RELATIVE

    # 判定：檔唔喺度 → 爆（唔准靜默）
    if system_prompt_path.is_file() is False:
        raise RuntimeError("system instructions unavailable: " + str(system_prompt_path))

    # 系統環境（root 檔——同一模式；相對名出廠固定）
    system_env_path = DATA_DIR / SYSTEM_ENV_RELATIVE

    # 判定：檔唔喺度 → 爆
    if system_env_path.is_file() is False:
        raise RuntimeError("system instructions unavailable: " + str(system_env_path))

    hints_sections = [
        system_prompt_path.read_text(encoding="utf-8"),
        system_env_path.read_text(encoding="utf-8"),
    ]

    # 專案環境（後備源——偵測失敗可缺；缺＝略過記行）
    project_env_path = DATA_DIR / "environment" / ("env." + message_store().get_project_slug() + ".md")

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
    _ = temporary_file.write_text(hints_text, encoding="utf-8")
    os.replace(temporary_file, hints_file)
    sc_logger.log("goose hints written: " + str(hints_file) + " (" + str(len(hints_text)) + " chars)")

def write_custom_provider_file(goose_path_root: str, model_name: str, context_length: int | None) -> None:
    """
    寫 goose 自訂 provider 檔（<GOOSE_PATH_ROOT>/config/custom_providers/<前綴>.json——goose 原生讀取位；
    只限自訂表內嘅 provider 前綴，原生／唔支持＝略過）。
    內容＝goose 官方 schema（engine＝openai 相容；models 只列當題模型；key 只寫環境變數名——值唔入檔）。
    每問一次刷新，內容無變唔重寫（原子寫：.tmp → replace）。
    """
    model_parts = model_name.partition("/")
    provider_prefix = model_parts[0]
    model_part = model_parts[2]

    # 判定：唔喺自訂表（原生／唔支持）→ 唔使寫
    if provider_prefix not in paths.CUSTOM_PROVIDER_TABLE:
        return

    provider_conf = paths.CUSTOM_PROVIDER_TABLE[provider_prefix]

    # 當題模型項（name＋context_limit——有值先落）
    model_item: dict[str, object] = {"name": model_part}

    # 判定：msg_conf 有帶 context_length → 落 context_limit（缺＝goose 自行推算）
    if context_length is not None:
        model_item["context_limit"] = context_length

    # goose 官方 schema（engine＝openai 相容——自訂 provider 統一型）
    provider_file_data: dict[str, object] = {
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
    _ = temporary_file.write_text(provider_file_text, encoding="utf-8")
    os.replace(temporary_file, provider_file_path)
    sc_logger.log("goose custom provider written: " + str(provider_file_path))

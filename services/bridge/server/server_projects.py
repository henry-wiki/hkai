#!/usr/bin/env python3
"""
server_projects.py — projects 端點。

職責：
- git_init()：POST /projects/git/init/（PHP 呼叫——執行 git init）
- git_stage_all()：POST /projects/git/stage_all/（PHP 呼叫——執行 git add -A）
"""

import datetime
import subprocess

from fastapi import APIRouter, Request

from ..runtime import sc_logger

router = APIRouter()


def run_git_command(project_path, git_arguments):
    """在指定路徑執行 git 命令——回傳 (是否成功, 訊息)。"""
    try:
        completed = subprocess.run(
            ["git", *git_arguments],
            cwd=project_path,
            capture_output=True,
            text=True,
            timeout=60,
            check=False,
        )
    except (OSError, subprocess.SubprocessError) as run_error:
        return False, str(run_error)

    # 非零退出碼 → 帶回 stderr 訊息
    if completed.returncode != 0:
        message = completed.stderr.strip() or completed.stdout.strip()
        return False, message

    return True, completed.stdout.strip()


def run_git_init(project_path):
    """初始化 git 儲存庫（已存在時 git init 亦為成功）＋套用標準 repo 設定。"""
    sc_logger.log(f"git init start: {project_path}")

    is_ok, message = run_git_command(project_path, ["init"])

    # git init 失敗 → 記錄＋原樣帶回錯誤
    if is_ok is False:
        sc_logger.log(f"git init failed: {project_path} — {message}")
        return False, message

    is_ok, message = run_git_init_standard_configs(project_path)

    # 標準設定失敗 → 記錄＋原樣帶回錯誤
    if is_ok is False:
        sc_logger.log(f"git init standard configs failed: {project_path} — {message}")
        return False, message

    sc_logger.log(f"git init done: {project_path}")

    return True, "git init 完成"


def run_git_init_standard_configs(project_path):
    """套用標準 repo 設定（用戶 2026-10-03 定：每新增一項都要寫明為什麼）。"""
    standard_configs = [
        # core.longpaths=true：Windows 路徑上限 260 字元，開啟後 git 用長路徑 API（~32k）
        #   為什麼：網頁 mirror／深層素材（如 Wix 下載）路徑經常超 260 字——唔開＝git add 直接 fatal
        ("core.longpaths", "true"),
        # core.autocrlf=false：唔准 git 轉行尾（檔案原樣入、原樣出）
        #   為什麼：一轉＝git 眼中成批檔「假變更」（本專案刻意混 EOL）；寫 local 最優先——蓋住 Git for Windows 安裝嘅 system true
        ("core.autocrlf", "false"),
    ]

    # 逐條套用——任何一條失敗即原樣帶回
    for config_key, config_value in standard_configs:
        is_ok, message = run_git_command(project_path, ["config", config_key, config_value])

        # 設定失敗 → 原樣帶回錯誤
        if is_ok is False:
            return False, message

    return True, "標準設定完成"


def run_git_stage_all(project_path):
    """加入全部變更到準備區（git add -A）。"""
    sc_logger.log(f"git add -A start: {project_path}")

    is_ok, message = run_git_command(project_path, ["add", "-A"])

    # git add -A 失敗 → 記錄＋原樣帶回錯誤
    if is_ok is False:
        sc_logger.log(f"git add -A failed: {project_path} — {message}")
        return False, message

    sc_logger.log(f"git add -A done: {project_path}")

    return True, "git add -A 完成"


def run_git_commit(project_path):
    """提交準備區（git commit——訊息自動＝update ＋ 本機時間）。"""
    commit_message = "update " + datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
    sc_logger.log(f"git commit start: {project_path} — {commit_message}")

    is_ok, message = run_git_command(project_path, ["commit", "-m", commit_message])

    # git commit 失敗 → 記錄＋原樣帶回錯誤
    if is_ok is False:
        sc_logger.log(f"git commit failed: {project_path} — {message}")
        return False, message

    sc_logger.log(f"git commit done: {project_path}")

    return True, "git commit 完成"


@router.post("/projects/git/init/")
def git_init(request: Request, payload: dict) -> dict:
    """PHP 執行 git init。"""
    project_path = payload.get("project_path")

    # 參數不足 → 失敗
    if not project_path:
        return {"ok": False, "error": "missing project_path"}

    is_ok, message = run_git_init(project_path)

    # 成功 → 回 ok + message
    if is_ok is True:
        return {"ok": True, "message": message}

    return {"ok": False, "error": message}


@router.post("/projects/git/stage_all/")
def git_stage_all(request: Request, payload: dict) -> dict:
    """PHP 執行 git add -A。"""
    project_path = payload.get("project_path")

    # 參數不足 → 失敗
    if not project_path:
        return {"ok": False, "error": "missing project_path"}

    is_ok, message = run_git_stage_all(project_path)

    # 成功 → 回 ok + message
    if is_ok is True:
        return {"ok": True, "message": message}

    return {"ok": False, "error": message}


@router.post("/projects/git/commit/")
def git_commit(request: Request, payload: dict) -> dict:
    """PHP 執行 git commit。"""
    project_path = payload.get("project_path")

    # 參數不足 → 失敗
    if not project_path:
        return {"ok": False, "error": "missing project_path"}

    is_ok, message = run_git_commit(project_path)

    # 成功 → 回 ok + message
    if is_ok is True:
        return {"ok": True, "message": message}

    return {"ok": False, "error": message}

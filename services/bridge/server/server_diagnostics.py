#!/usr/bin/env python3
"""
server_diagnostics.py — diagnostics 端點。

職責：
- get_git_status()：POST /diagnostics/git/get_status（PHP 呼叫——執行 Git 狀態診斷）
- get_config_list()：POST /diagnostics/git/get_config_list（PHP 呼叫——Git 設定一覽（帶 project_path））
- clean_repo_ghosts()：POST /diagnostics/git/clean_repo_ghosts（PHP 呼叫——發問前預檢：清 git 幽靈）
- get_system_env()：POST /diagnostics/system_env/get（PHP 呼叫——執行系統級環境偵測）
- get_project_env()：POST /diagnostics/project_env/get（PHP 呼叫——執行專案級環境偵測（帶 project_path））
- get_long_paths()：POST /diagnostics/files/get_long_paths（PHP 呼叫——檔案路徑長度掃描（帶 project_path））
"""

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportUnknownParameterType=false, reportExplicitAny=false, reportAny=false, reportImplicitRelativeImport=false
# （返回 dict 由 diagnostics 提供——Unknown/Any 警告一律關，其餘照檢）

from typing import Any

from diagnostics.main import diagnostics_class
from fastapi import APIRouter

router = APIRouter()

diagnostics = diagnostics_class()

@router.post("/diagnostics/git/get_status")
def get_git_status(payload: dict[str, Any]) -> dict[str, Any]:
    """PHP 執行 Git 狀態診斷。"""
    project_path = payload.get("project_path")
    
    if not project_path:
        return {"ok": False, "error": "missing project_path"}
    
    result = diagnostics.get_git_status(project_path)
    return result

@router.post("/diagnostics/git/get_config_list")
def get_config_list(payload: dict[str, Any]) -> dict[str, Any]:
    """PHP 執行 Git 設定一覽（帶 project_path——該專案嘅 git config 攤開）。"""
    project_path = payload.get("project_path")

    if not project_path:
        return {"ok": False, "error": "missing project_path"}

    result = diagnostics.get_config_list(project_path)
    return result


@router.post("/diagnostics/git/clean_repo_ghosts")
def clean_repo_ghosts(payload: dict[str, Any]) -> dict[str, Any]:
    """PHP 發問前預檢——清 git 幽靈（名單有、磁碟冇）。"""
    project_path = payload.get("project_path")

    if not project_path:
        return {"ok": False, "error": "missing project_path"}

    result = diagnostics.clean_repo_ghosts(project_path)
    return result


@router.post("/diagnostics/system_env/get")
def get_system_env() -> dict[str, Any]:
    """PHP 執行系統級環境偵測（無參數——整台機器一份）。"""
    result = diagnostics.get_system_env()
    return result

@router.post("/diagnostics/project_env/get")
def get_project_env(payload: dict[str, Any]) -> dict[str, Any]:
    """PHP 執行專案級環境偵測（帶 project_path——偵測該專案目錄）。"""
    project_path = payload.get("project_path")

    if not project_path:
        return {"ok": False, "error": "missing project_path"}

    result = diagnostics.get_project_env(project_path)
    return result


@router.post("/diagnostics/files/get_long_paths")
def get_long_paths(payload: dict[str, Any]) -> dict[str, Any]:
    """PHP 執行檔案路徑長度掃描（帶 project_path——掃描該專案目錄）。"""
    project_path = payload.get("project_path")

    if not project_path:
        return {"ok": False, "error": "missing project_path"}

    result = diagnostics.get_long_paths(project_path)
    return result

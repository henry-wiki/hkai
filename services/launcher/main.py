#!/usr/bin/env python3
"""
main.py

launcher 入口（薄——參數處理＋模式派發）。

執行方式（由 PHP 同步 exec 冷啟動；cwd = services）：

    python -u -m launcher.main <daemon> [--bg] [--runtime <runtime.json 全路徑>]

- <daemon>＝目標 daemon 關鍵字（例："bridge"）——launcher 自砌 `python -u -m {daemon}.main`
  （python 執行檔／`-u`／`-m`／模組名＝機械嘢，一律唔使傳）
- --bg＝非阻塞（開咗即返——無視窗／新程序群組／新 session；只確認「開到」）
- 無 --bg＝阻塞（等完工——exit code 原樣帶返；stdout／stderr 流返俾呼叫端）
- --runtime <全路徑>＝傳俾 daemon 嘅 runtime.json（機器層值——來源＝PHP 單一來源，唔喺度自推）
- 其餘參數＝原樣傳俾 daemon

例（拋出：拉 bridge）：

    python -u -m launcher.main bridge --bg --runtime <runtime.json>

例（阻塞）：

    python -u -m launcher.main bridge --runtime <runtime.json>
"""

import subprocess
import sys
from pathlib import Path

# 套件根目錄（services——launcher/ 上一層）
_SERVICES_DIR = Path(__file__).resolve().parents[1]


def spawn_detached(command: list) -> None:
    """以 detached 方式開命令（唔等佢；開到即退——只確認「開到」）。"""

    # 判定：Windows → 無視窗＋新程序群組；POSIX → 新 session（皆脫離 PHP 請求生命週期）
    # 註：不可用 DETACHED_PROCESS——console 程式（python.exe）會被 Windows 新開一個可見黑窗
    if sys.platform == "win32":
        creation_flags = subprocess.CREATE_NO_WINDOW | subprocess.CREATE_NEW_PROCESS_GROUP
        subprocess.Popen(
            command,
            cwd=str(_SERVICES_DIR),
            creationflags=creation_flags,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
    else:
        subprocess.Popen(
            command,
            cwd=str(_SERVICES_DIR),
            start_new_session=True,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )


def run_blocking(command: list) -> int:
    """等命令完工（stdio 繼承——輸出流返俾呼叫端）；回傳 exit code。"""
    return subprocess.call(command, cwd=str(_SERVICES_DIR))


def main() -> int:
    """CLI 入口：python -u -m launcher.main <daemon> [--bg] [--runtime <path>]。"""
    # 判定：冇目標 daemon 關鍵字（非 0 退出——PHP 側據此判失敗）
    if len(sys.argv) < 2:
        print("usage: python -u -m launcher.main <daemon> [--bg] [--runtime <path>]", file=sys.stderr)
        return 2

    daemon_keyword = sys.argv[1]
    remaining_args = sys.argv[2:]

    is_background = False
    runtime_path = ""
    extra_args = []

    # 逐粒掃參數：--bg＝非阻塞掣（有＝非阻塞／冇＝阻塞）；--runtime＝食下一粒做值；其餘＝原樣傳俾 daemon
    arg_index = 0
    while arg_index < len(remaining_args):
        current_arg = remaining_args[arg_index]

        if current_arg == "--bg":
            is_background = True
            arg_index += 1
            continue

        if current_arg == "--runtime" and arg_index + 1 < len(remaining_args):
            runtime_path = remaining_args[arg_index + 1]
            arg_index += 2
            continue

        extra_args.append(current_arg)
        arg_index += 1

    # daemon 命令（機械嘢由 launcher 砌：python 執行檔／-u／-m／模組名）
    command = [sys.executable, "-u", "-m", daemon_keyword + ".main"]

    # 判定：有 --runtime → 傳俾 daemon（唯一動態設定入口）
    if runtime_path != "":
        command.append(runtime_path)

    command += extra_args

    # 判定：--bg → 非阻塞（開到即返）
    if is_background:
        spawn_detached(command)
        return 0

    # 無 --bg → 阻塞（等完工——exit code 原樣帶返）
    return run_blocking(command)


if __name__ == "__main__":
    sys.exit(main())

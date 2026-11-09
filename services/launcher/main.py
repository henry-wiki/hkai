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

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false
# （psutil 冇 type stub——「類型部分未知」一律關，其餘照檢）

import json
import os
import subprocess
import sys
from typing import cast

import psutil

from bootstrap import sc_logger
from .context import HEARTBEAT_DIR, SERVICES_DIR

# 開 --bg 前要清場嘅目標（只有長駐 daemon；工具類——如 image_extractor——唔清場）
_SWEEP_DAEMON_KEYWORDS = ["bridge", "goose_daemon"]

# terminate 後等幾秒（逾時即 kill）
_KILL_WAIT_SEC = 3.0


def spawn_detached(command: list[str]) -> None:
    """以 detached 方式開命令（唔等佢；開到即退——只確認「開到」）。"""

    # 判定：Windows → 無視窗＋新程序群組；POSIX → 新 session（皆脫離 PHP 請求生命週期）
    # 註：不可用 DETACHED_PROCESS——console 程式（python.exe）會被 Windows 新開一個可見黑窗
    if sys.platform == "win32":
        creation_flags = subprocess.CREATE_NO_WINDOW | subprocess.CREATE_NEW_PROCESS_GROUP

        _ = subprocess.Popen(
            command,
            cwd=str(SERVICES_DIR),
            creationflags=creation_flags,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )

    else:
        _ = subprocess.Popen(
            command,
            cwd=str(SERVICES_DIR),
            start_new_session=True,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )


def run_blocking(command: list[str]) -> int:
    """等命令完工（stdio 繼承——輸出流返俾呼叫端）；回傳 exit code。"""
    return subprocess.call(command, cwd=str(SERVICES_DIR))


def is_target_daemon_process(process_info: dict[str, object], daemon_program_name: str) -> bool:
    """判定：python 執行檔＋cmdline 含「-m <目標模組>」＋cwd＝本 services 目錄（同一實例）。"""
    raw_cmdline = process_info.get("cmdline")
    process_cmdline = cast(list[str], raw_cmdline) if isinstance(raw_cmdline, list) else []

    # 判定：冇 cmdline（系統程序）→ 唔係
    if len(process_cmdline) == 0:
        return False

    executable_name = str(process_cmdline[0]).lower()

    # 判定：第一粒唔係 python 執行檔（shell／工具提及字樣）→ 唔係
    if "python" not in executable_name:
        return False

    # 判定：cwd 唔同（別個站／別個實例）→ 唔係
    if process_info.get("cwd") != str(SERVICES_DIR):
        return False

    cmdline_text = " ".join(process_cmdline)

    # 判定：cmdline 以「-m <目標模組>」啟動 → 係（其他 daemon 一律唔掂）
    if ("-m " + daemon_program_name) in cmdline_text:
        return True

    return False


def terminate_process(process: psutil.Process) -> None:
    """先 terminate、逾時即 kill（每一步寫 stderr）。"""
    process_pid = process.pid

    try:
        _ = process.terminate()
        _ = process.wait(timeout=_KILL_WAIT_SEC)
        print("launcher: terminated stale pid " + str(process_pid), file=sys.stderr)
    except psutil.TimeoutExpired:
        _ = process.kill()
        print("launcher: killed stale pid " + str(process_pid), file=sys.stderr)
    except psutil.Error:
        print("launcher: cannot stop stale pid " + str(process_pid), file=sys.stderr)


def sweep_orphan_processes(daemon_keyword: str) -> None:
    """殺同名舊 daemon（同一 services 目錄；唔殺自己／唔殺其他 daemon）。"""
    self_pid = os.getpid()
    daemon_program_name = daemon_keyword + ".main"

    for process in psutil.process_iter(["pid", "cmdline", "cwd"]):
        # 判定：程序喺迭代途中消失／無權限 → 跳（唔好拖死整個 launcher）
        try:
            process_info = cast(dict[str, object], process.as_dict(attrs=["pid", "cmdline", "cwd"]))
        except psutil.Error:
            continue

        # 判定：自己 → 跳
        if process_info.get("pid") == self_pid:
            continue

        # 判定：唔係同名 daemon → 跳（bridge 唔會因起 goose 而被殺）
        if is_target_daemon_process(process_info, daemon_program_name) is False:
            continue

        terminate_process(process)


def remove_dead_heartbeat_files() -> None:
    """清死心跳檔（<program>.<pid>.json——該 pid 已唔存在）。"""
    # 判定：心跳目錄唔存在 → 冇嘢清
    if HEARTBEAT_DIR.is_dir() is False:
        return

    for heartbeat_file in HEARTBEAT_DIR.glob("*.json"):
        pid_text = heartbeat_file.stem.rsplit(".", 1)[-1]

        # 判定：檔名尾段唔係數字 → 跳
        if pid_text.isdigit() is False:
            continue

        # 判定：該 pid 仍在生 → 保留
        if psutil.pid_exists(int(pid_text)):
            continue

        try:
            heartbeat_file.unlink()
        except OSError:
            continue


def clear_stale_processes(daemon_keyword: str) -> None:
    """spawn 前清場（同名舊 daemon → 死心跳檔）。"""
    sweep_orphan_processes(daemon_keyword)
    remove_dead_heartbeat_files()


def main() -> int:
    """CLI 入口：python -u -m launcher.main <daemon> [--bg] [--runtime <path>]。"""
    sc_logger.setup("launcher")
    # 判定：冇目標 daemon 關鍵字（非 0 退出——PHP 側據此判失敗）
    if len(sys.argv) < 2:
        print("usage: python -u -m launcher.main <daemon> [--bg] [--runtime <path>]", file=sys.stderr)
        return 2

    daemon_keyword = sys.argv[1]
    remaining_args = sys.argv[2:]

    # 判定：cleanup 子命令（清道夫——PHP 只傳命令、唔傳 pid；自己掃心跳檔核對＋殺＋清檔）
    if daemon_keyword == "cleanup":
        from .cleanup import cleanup_class

        cleanup_report = cleanup_class().clean_orphans()
        print(json.dumps(cleanup_report, ensure_ascii=False))

        return 0

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

    # 判定：--bg → 非阻塞（spawn 前清場：舊 daemon＋死心跳檔——開到即返）
    if is_background:
        # 判定：目標係長駐 daemon → 先清場（工具類唔清——避免誤殺正在運行嘅 daemon）
        if daemon_keyword in _SWEEP_DAEMON_KEYWORDS:
            clear_stale_processes(daemon_keyword)

        spawn_detached(command)
        return 0

    # 無 --bg → 阻塞（等完工——exit code 原樣帶返）
    return run_blocking(command)


if __name__ == "__main__":
    sys.exit(main())

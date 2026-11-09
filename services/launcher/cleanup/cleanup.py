#!/usr/bin/env python3
"""cleanup.py — 心跳清道夫（自行掃描 → 核對 create_time → 殺 → 清檔 → 寫 log）。

上游：PHP `launcher()->exec(["cleanup"])`（stop_server 發現 bridge 已死時——只傳一個命令，唔傳 pid）
下游：data/process_status/*.json（心跳檔）＋ psutil（核對建立時間／殺程序）＋ bootstrap/sc_logger（記錄）
"""

# pyright: reportUnknownMemberType=false, reportUnknownVariableType=false, reportUnknownArgumentType=false, reportExplicitAny=false, reportAny=false

import json
from pathlib import Path
from typing import Any, cast

import psutil

from bootstrap import sc_logger
from ..context import HEARTBEAT_DIR

# create_time 容差（毫秒——Windows 開機時間精度誤差）
CREATE_TIME_TOLERANCE_MS = 1000

# terminate 後等幾秒（逾時即 kill）
KILL_WAIT_SEC = 3.0


class cleanup_class:
    """心跳清道夫（單一職責：掃心跳檔、核對建立時間、殺殘骸、清死檔）。"""

    def __init__(self):
        pass

    def clean_orphans(self) -> dict[str, Any]:
        """掃全部心跳檔：pid 死活 → 建時間核對 → 殺／清檔；回報告。"""
        report: dict[str, Any] = {}
        report["ok"] = True
        report["killed_pids"] = []
        report["removed_files"] = []
        report["skipped"] = []

        # 判定：心跳目錄唔存在 → 冇嘢清
        if HEARTBEAT_DIR.is_dir() is False:
            return report

        for heartbeat_file in sorted(HEARTBEAT_DIR.glob("*.json")):
            heartbeat_record = self.read_heartbeat_file(heartbeat_file)

            # 判定：壞檔／格式唔對 → 交返人手（唔殺、唔刪）
            if heartbeat_record is None:
                report["skipped"].append({"file": heartbeat_file.name, "reason": "unreadable"})
                continue

            process_pid = int(heartbeat_record.get("pid", 0) or 0)

            # 判定：pid 唔合法 → 跳
            if process_pid <= 0:
                report["skipped"].append({"file": heartbeat_file.name, "reason": "invalid pid"})
                continue

            # 判定：程序已死 → 刪死檔
            if psutil.pid_exists(process_pid) is False:
                self.write_log("INFO", "remove stale heartbeat " + heartbeat_file.name + " (pid " + str(process_pid) + " gone)")

                if self.remove_heartbeat_file(heartbeat_file):
                    report["removed_files"].append(heartbeat_file.name)
                continue

            # 判定：create_time 對唔上（pid 被重用）→ 刪過期檔，唔殺
            if self.is_create_time_matched(heartbeat_record) is False:
                self.write_log("WARN", "remove stale heartbeat " + heartbeat_file.name + " (create_time mismatch)")

                if self.remove_heartbeat_file(heartbeat_file):
                    report["removed_files"].append(heartbeat_file.name)

                report["skipped"].append({"file": heartbeat_file.name, "reason": "create_time mismatch"})
                continue

            # 對得上 → 殺（落刀前寫 log）
            image_name = self.get_process_image_name(process_pid)
            self.write_log("INFO", "kill pid " + str(process_pid) + " image " + image_name + " (create_time matched)")

            if self.kill_verified_process(process_pid):
                report["killed_pids"].append(process_pid)

                if self.remove_heartbeat_file(heartbeat_file):
                    report["removed_files"].append(heartbeat_file.name)
                continue

            self.write_log("ERROR", "kill failed pid " + str(process_pid))
            report["skipped"].append({"file": heartbeat_file.name, "reason": "kill failed"})

        return report

    def read_heartbeat_file(self, heartbeat_file: Path) -> "dict[str, Any] | None":
        """讀一個心跳檔（壞檔／非物件 → None）。"""
        try:
            raw_text = heartbeat_file.read_text(encoding="utf-8")
            heartbeat_record = json.loads(raw_text)
        except (OSError, ValueError):
            return None

        # 判定：頂層唔係 object → 唔當心跳
        if isinstance(heartbeat_record, dict) is False:
            return None

        return cast(dict[str, Any], heartbeat_record)

    def is_create_time_matched(self, heartbeat_record: dict[str, Any]) -> bool:
        """核對 pid 嘅實際建立時間（int 毫秒 ±1000ms）＝心跳記錄。"""
        expected_create_time = heartbeat_record.get("create_time", 0)
        process_pid = int(heartbeat_record.get("pid", 0) or 0)

        # 判定：冇記錄 create_time／pid 唔合法 → 唔配對
        if isinstance(expected_create_time, int) is False:
            return False
        if expected_create_time <= 0:
            return False
        if process_pid <= 0:
            return False

        try:
            actual_create_time_ms = int(psutil.Process(process_pid).create_time() * 1000)
        except psutil.Error:
            return False

        return abs(actual_create_time_ms - expected_create_time) <= CREATE_TIME_TOLERANCE_MS

    def kill_verified_process(self, process_pid: int) -> bool:
        """先 terminate、逾時即 kill（回傳成功與否）。"""
        try:
            process = psutil.Process(process_pid)
            _ = process.terminate()
            _ = process.wait(timeout=KILL_WAIT_SEC)

            return True
        except psutil.TimeoutExpired:
            try:
                _ = psutil.Process(process_pid).kill()
            except psutil.Error:
                return False

            return True
        except psutil.Error:
            return False

    def write_log(self, log_level: str, message_text: str) -> None:
        """記錄一行（統一 sc_logger → data/logs/launcher.{audit,error}.log）。"""
        log_message = "cleanup: " + message_text

        # 判定：ERROR 級 → error.log（同時 audit）
        if log_level == "ERROR":
            sc_logger.error(log_message)
            return

        sc_logger.log(log_message)

    def get_process_image_name(self, process_pid: int) -> str:
        """取程序映像名（讀唔到 → 空字串）。"""
        try:
            return psutil.Process(process_pid).name()
        except psutil.Error:
            return ""

    def remove_heartbeat_file(self, heartbeat_file: Path) -> bool:
        """刪一個心跳檔（失敗回 false）。"""
        try:
            heartbeat_file.unlink()
        except OSError:
            return False

        return True

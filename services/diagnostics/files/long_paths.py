"""專案檔案路徑長度診斷（唯讀——由 PHP 經 bridge 呼叫；stdout 只輸出單行 JSON）。

用法：
    python -m diagnostics.files.long_paths <project_path>

輸出（stdout，單行 JSON——成功）：
    {
      "ok": true,
      "scanned_count": 1087,         # 掃描檔案總數（所有檔）
      "warning_count": 46,           # 超過 200 字元（201–220——警告）
      "danger_count": 210,           # 超過 220 字元（221–250——危險）
      "critical_count": 185,         # 超過 250 字元（極度危險）
      "elapsed_ms": 91,              # 掃描耗時（毫秒）
      "truncated": true,             # 問題清單是否被截（超過顯示上限）
      "problems": [                  # 問題清單（長度由長至短；上限 ITEM_LIMIT 筆）
        {"path": "相對路徑", "length": 331, "level": "warning" | "danger" | "critical"}
      ]
    }

輸出（stdout，單行 JSON——失敗）：
    {"ok": false, "error": "..."}

設計：掃描一律在 Python 層執行（os.scandir 一趟——唔讀檔內容，只計路徑長度）；
      長度＝完整絕對路徑字元數（Windows 工具 260 上限嘅比較基準）；
      不跟隨符號連結（防循環）；問題清單由 Python 封頂（回應大小自我保護）。
"""

import json
import os
import sys
import time

# 警告門檻（完整路徑字元數——超過 200 進入警告）
WARN_LENGTH = 200

# 危險門檻（超過 220）
DANGER_LENGTH = 220

# 極度危險門檻（超過 250）
CRITICAL_LENGTH = 250

# 問題清單顯示上限（超出＝截——計數照全量）
ITEM_LIMIT = 200


class long_paths_class:
    def __init__(self):
        pass

    def collect_problem(self, entry_path):
        """單檔判定——超過門檻回問題記錄（否則 None）。"""
        path_length = len(entry_path)

        # 未超過警告門檻 → 唔算問題
        if path_length <= WARN_LENGTH:
            return None

        level = "warning"

        # 超過危險門檻 → danger
        if path_length > DANGER_LENGTH:
            level = "danger"

        # 超過極度危險門檻 → critical
        if path_length > CRITICAL_LENGTH:
            level = "critical"

        return {"path": entry_path, "length": path_length, "level": level}

    def collect_problems(self, project_path):
        """全樹掃描一趟（os.scandir——唔讀檔內容）——回 (問題全集, 掃描檔數)。"""
        root_path = project_path.rstrip("\\/")
        root_length = len(root_path)
        problems = []
        scanned_count = 0
        pending_dirs = [root_path]

        # 逐目錄走（顯式棧——避遞迴深度限制）
        while pending_dirs:
            dir_path = pending_dirs.pop()

            try:
                dir_entries = os.scandir(dir_path)
            except OSError:
                continue

            with dir_entries as scan:
                for entry in scan:
                    try:
                        is_dir = entry.is_dir(follow_symlinks=False)
                    except OSError:
                        continue

                    # 目錄 → 排隊續走
                    if is_dir is True:
                        pending_dirs.append(entry.path)
                        continue

                    scanned_count += 1

                    problem = self.collect_problem(entry.path)

                    # 未過門檻 → 唔收
                    if problem is None:
                        continue

                    # 顯示用＝相對路徑（長度照完整路徑計）
                    problem["path"] = entry.path[root_length + 1:]
                    problems.append(problem)

        problems.sort(key=lambda problem: problem["length"], reverse=True)

        return problems, scanned_count

    def get_long_paths(self, project_path):
        """掃描專案——回完整結果（含計數、耗時、問題清單）。"""
        # 路徑唔存在 → 失敗
        if not os.path.isdir(project_path):
            return {"ok": False, "error": "project path not found"}

        started_at = time.perf_counter()

        problems, scanned_count = self.collect_problems(project_path)

        elapsed_ms = int((time.perf_counter() - started_at) * 1000)

        warning_count = 0
        danger_count = 0
        critical_count = 0

        # 逐條計數（級別分桶）
        for problem in problems:
            # 極度危險 → critical 桶
            if problem["level"] == "critical":
                critical_count += 1
                continue
            # 危險 → danger 桶
            if problem["level"] == "danger":
                danger_count += 1
                continue
            warning_count += 1

        truncated = len(problems) > ITEM_LIMIT

        result = {}
        result["ok"] = True
        result["scanned_count"] = scanned_count
        result["warning_count"] = warning_count
        result["danger_count"] = danger_count
        result["critical_count"] = critical_count
        result["elapsed_ms"] = elapsed_ms
        result["truncated"] = truncated
        result["problems"] = problems[:ITEM_LIMIT]

        return result

    def main(self):
        # stdout 強制 UTF-8（Windows 預設 cp950——PHP 側固定收 UTF-8 JSON）
        sys.stdout.reconfigure(encoding="utf-8")

        # 參數不足 → 用法錯誤
        if len(sys.argv) < 2:
            print(json.dumps({"ok": False, "error": "usage: long_paths.py <project_path>"}))
            return

        result = self.get_long_paths(sys.argv[1])
        print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    long_paths_class().main()

"""專案 Git 設定一覽診斷（唯讀——由 PHP 經 bridge 呼叫；stdout 只輸出單行 JSON）。

用法：
    python -m diagnostics.git.config_list <project_path>

輸出（stdout，單行 JSON——成功）：
    {
      "ok": true,
      "is_repo": true,                  # 是否 git 工作樹
      "count": 42,                      # 設定項總數
      "elapsed_ms": 12,                 # 執行耗時（毫秒）
      "configs": [                      # 設定清單（git config --list 順序）
        {"key": "user.name", "value": "..."}
      ]
    }

輸出（stdout，單行 JSON——失敗）：
    {"ok": false, "error": "..."}

設計：一律在 Python 層執行（rev-parse 探測＋git config --list——唯讀）；
      設定值可含「=」——只切第一個等號；冇值項（如 core.bare）＝空字串；
      is_repo 判斷同 git_status 版一致（rev-parse --is-inside-work-tree）。
"""

import json
import subprocess
import sys
import time


class config_list_class:
    def __init__(self):
        pass

    def check_is_git_work_tree(self, project_path):
        """偵測路徑是否 git 工作樹（rev-parse --is-inside-work-tree——同 git_status 版一致）。"""
        # git rev-parse 探測（唯讀——非 git 目錄即失敗）
        try:
            completed = subprocess.run(
                ["git", "rev-parse", "--is-inside-work-tree"],
                cwd=project_path,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
            )
        except OSError:
            return False

        # 判定：命令失敗（路徑唔存在／非 repo）→ 非工作樹
        if completed.returncode != 0:
            return False

        return completed.stdout.strip() == "true"

    def get_config_list(self, project_path):
        """執行 rev-parse 探測＋git config --list（工作目錄＝專案）→ 設定清單（key／value）。"""
        start_time = time.time()

        is_repo = self.check_is_git_work_tree(project_path)

        # git config 讀取（唯讀——非 git 目錄照讀全域設定）
        try:
            completed = subprocess.run(
                ["git", "config", "--list"],
                cwd=project_path,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
            )
        except OSError as error:
            return {"ok": False, "error": str(error)}

        # 判定：git 執行失敗 → 回錯誤（stderr 為準）
        if completed.returncode != 0:
            error_text = completed.stderr.strip()
            return {"ok": False, "error": error_text or "git config failed"}

        elapsed_ms = int((time.time() - start_time) * 1000)

        configs = []

        # 逐行拆解（key=value——只切第一個等號）
        for config_line in completed.stdout.splitlines():
            config_line = config_line.strip()

            # 判定：空行 → 跳過
            if not config_line:
                continue

            # 判定：冇等號（如 core.bare）→ 值＝空字串
            if "=" not in config_line:
                configs.append({"key": config_line, "value": ""})
                continue

            config_key, config_value = config_line.split("=", 1)
            configs.append({"key": config_key, "value": config_value})

        result = {}
        result["ok"] = True
        result["is_repo"] = is_repo
        result["count"] = len(configs)
        result["elapsed_ms"] = elapsed_ms
        result["configs"] = configs

        return result

    def main(self):
        # stdout 強制 UTF-8（Windows 預設 cp950——PHP 側固定收 UTF-8 JSON）
        sys.stdout.reconfigure(encoding="utf-8")

        # 判定：參數不足 → 用法錯誤
        if len(sys.argv) < 2:
            print(json.dumps({"ok": False, "error": "usage: config_list.py <project_path>"}))
            return

        result = self.get_config_list(sys.argv[1])
        print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    config_list_class().main()

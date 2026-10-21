"""專案 Git 診斷（唯讀——由 PHP exec 呼叫；stdout 只輸出單行 JSON）。

用法：
    python -m diagnostics.git.git_status <project_path>

輸出（stdout，單行 JSON——成功）：
    {
      "ok": true,
      "git_installed": true,
      "git_version": "git version 2.47.0.windows.1",
      "path_exists": true,
      "has_gitignore": true,
      "is_repo": true,
      "branch": "master",              # detached 時為空字串
      "detached": false,
      "head_sha": "10b6eec...",        # 空 repo 為 "(initial)"
      "has_commit": true,
      "upstream": "origin/master",     # 無 upstream 為空字串
      "ahead": 0,                      # 基於本地 upstream ref（未 fetch——可能過時）
      "behind": 0,
      "changes": {"staged": 0, "unstaged": 3, "untracked": 2, "conflicts": 0, "added": 0, "deleted": 1, "modified": 2, "renamed": 0, "total": 5},
      "diff": {"additions": 23, "deletions": 5},             # vs HEAD（已追蹤檔；無提交 = null；無變動 = 0/0 直接回——唔 spawn）
      "last_commit": {"sha": "...", "committed_at": "...", "subject": "..."},   # 無提交 = null
      "recent_commits": [{"sha": "...", "committed_at": "...", "subject": "..."}],   # 最近 5 筆；無提交 = []
      "remote_origin": "git@github.com:..."                  # 無 origin 為空字串
    }

輸出（stdout，單行 JSON——失敗）：
    {"ok": false, "error": "..."}

設計：git 動作一律在 Python 層執行（跨平台一致），PHP 只負責 exec 與讀 JSON；
      一律 --no-optional-locks（唯讀——不寫 .git/index）；不 fetch（無網絡副作用）。
"""

import json
import os
import subprocess
import sys

# 單一 git 命令逾時（秒）——大 repo／慢碟自我保護
GIT_TIMEOUT_SECONDS = 15

# 最近提交清單長度（Git tab 顯示用）
RECENT_COMMIT_COUNT = 5


class git_status_class:
    def __init__(self):
        pass

    def run_git_command(self, project_path, git_arguments):
        """在指定路徑執行 git 命令（唯讀 flags）——回傳 (是否成功, stdout, stderr)。"""
        command = ["git", "-C", project_path, "--no-optional-locks", *git_arguments]

        # 環境：GIT_CEILING_DIRECTORIES＝上一層——封死向上搜尋 .git
        command_env = dict(os.environ)
        command_env["GIT_CEILING_DIRECTORIES"] = os.path.dirname(os.path.normpath(project_path))

        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=GIT_TIMEOUT_SECONDS,
                check=False,
                env=command_env,
            )
        except (OSError, subprocess.SubprocessError) as run_error:
            return False, "", str(run_error)

        # 非零退出碼 → 失敗（stderr 帶原因）
        if completed.returncode != 0:
            return False, completed.stdout, completed.stderr.strip()

        return True, completed.stdout, completed.stderr

    def collect_git_version(self):
        """偵測 git 是否已安裝——回傳版本字串（未安裝回空字串）。"""
        try:
            completed = subprocess.run(
                ["git", "--version"],
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=GIT_TIMEOUT_SECONDS,
                check=False,
            )
        except (OSError, subprocess.SubprocessError):
            return ""

        # 非零退出碼 → 未安裝
        if completed.returncode != 0:
            return ""

        return completed.stdout.strip()

    def check_is_git_work_tree(self, project_path):
        """偵測路徑是否 git 工作樹（先確認自己目錄有 .git——唔准向上搵父層）。"""
        # 判定：專案目錄唔存在 → 非工作樹
        if not os.path.isdir(project_path):
            return False

        git_marker_path = os.path.join(project_path, ".git")

        # 判定：自己目錄冇 .git（檔案或目錄）→ 非工作樹（唔借上層 repo）
        if not os.path.exists(git_marker_path):
            return False

        is_ok, stdout_text, _ = self.run_git_command(project_path, ["rev-parse", "--is-inside-work-tree"])

        # 命令失敗（路徑唔存在／非 repo）→ 非工作樹
        if is_ok is False:
            return False

        # repo 根（--show-toplevel）——必須等於專案目錄（防上層 repo 冒充：空資料夾會搵到父層 .git）
        is_root_ok, root_stdout_text, _ = self.run_git_command(project_path, ["rev-parse", "--show-toplevel"])

        # 命令失敗 → 非工作樹
        if is_root_ok is False:
            return False

        normalized_project_path = os.path.normcase(os.path.normpath(project_path))
        normalized_repo_root    = os.path.normcase(os.path.normpath(root_stdout_text.strip()))

        # 判定：repo 根唔等於專案目錄 → 當唔係 repo（唔借父層）
        if normalized_project_path != normalized_repo_root:
            return False

        return stdout_text.strip() == "true"

    def parse_status_records(self, status_stdout):
        """解析 git status --porcelain=v2 --branch -uall -z → 分支／開叉／變更統計。"""
        status_summary = {}
        status_summary["branch"] = ""
        status_summary["detached"] = False
        status_summary["head_sha"] = ""
        status_summary["upstream"] = ""
        status_summary["ahead"] = 0
        status_summary["behind"] = 0
        status_summary["staged"] = 0
        status_summary["unstaged"] = 0
        status_summary["untracked"] = 0
        status_summary["conflicts"] = 0
        status_summary["added"] = 0
        status_summary["deleted"] = 0
        status_summary["modified"] = 0
        status_summary["renamed"] = 0
        status_summary["total"] = 0

        status_records = status_stdout.split("\0")
        record_index = 0

        # 逐記錄解析（-z：NUL 分隔——改名記錄佔兩個 token）
        while record_index < len(status_records):
            status_record = status_records[record_index]
            record_index += 1

            # 空記錄（結尾）→ 跳過
            if status_record == "":
                continue

            # 分支標頭：HEAD oid
            if status_record.startswith("# branch.oid "):
                status_summary["head_sha"] = status_record[len("# branch.oid "):]
                continue
            # 分支標頭：目前分支名（detached 時值為 (detached)）
            if status_record.startswith("# branch.head "):
                branch_head = status_record[len("# branch.head "):]
                if branch_head == "(detached)":
                    status_summary["detached"] = True
                else:
                    status_summary["branch"] = branch_head
                continue
            # 分支標頭：upstream 名
            if status_record.startswith("# branch.upstream "):
                status_summary["upstream"] = status_record[len("# branch.upstream "):]
                continue
            # 分支標頭：ahead／behind（+N／-M）
            if status_record.startswith("# branch.ab "):
                ab_text = status_record[len("# branch.ab "):]
                for ab_token in ab_text.split(" "):
                    if ab_token.startswith("+"):
                        status_summary["ahead"] = int(ab_token[1:])
                    if ab_token.startswith("-"):
                        status_summary["behind"] = int(ab_token[1:])
                continue

            # 未追蹤（?）
            if status_record.startswith("? "):
                status_summary["untracked"] += 1
                status_summary["total"] += 1
                continue
            # 忽略（!）→ 唔計
            if status_record.startswith("! "):
                continue
            # 衝突（u）
            if status_record.startswith("u "):
                status_summary["conflicts"] += 1
                status_summary["total"] += 1
                continue
            # 一般變更（1）／改名或複製（2）
            if status_record.startswith(("1 ", "2 ")):
                record_fields = status_record.split(" ")
                xy_text = record_fields[1] if len(record_fields) > 1 else ".."
                # XY 兩碼齊備才可判定 staged／unstaged
                if len(xy_text) >= 2:
                    if xy_text[0] != ".":
                        status_summary["staged"] += 1
                    if xy_text[1] != ".":
                        status_summary["unstaged"] += 1
                    # 檔案級分類（新增／刪除／改名／修改——供顯示統計；每檔歸一桶）
                    if "A" in xy_text:
                        status_summary["added"] += 1
                    elif "D" in xy_text:
                        status_summary["deleted"] += 1
                    elif "R" in xy_text:
                        status_summary["renamed"] += 1
                    else:
                        status_summary["modified"] += 1
                status_summary["total"] += 1
                # -z 格式：改名／複製記錄（2）→ 下一個 token 為原路徑（消費掉）
                if status_record.startswith("2 ") and record_index < len(status_records):
                    record_index += 1
                continue

        return status_summary

    def collect_changes(self, project_path):
        """收集工作區變更（status --porcelain=v2 --branch -uall -z；-uall＝逐檔展開·唔摺目錄）。"""
        is_ok, stdout_text, _ = self.run_git_command(
            project_path, ["status", "--porcelain=v2", "--branch", "-uall", "-z"])
        if is_ok is False:
            return None

        return self.parse_status_records(stdout_text)

    def collect_diff_summary(self, project_path):
        """收集未提交改動嘅 +/− 行數（diff --numstat HEAD -z；二進位檔唔計）。"""
        is_ok, stdout_text, _ = self.run_git_command(project_path, ["diff", "--numstat", "HEAD", "-z"])
        if is_ok is False:
            return None

        additions_count = 0
        deletions_count = 0
        numstat_records = stdout_text.split("\0")

        # 逐記錄加總（add\tdelete\tpath）
        for numstat_record in numstat_records:
            # 空記錄 → 跳過
            if numstat_record == "":
                continue
            numstat_fields = numstat_record.split("\t")
            # 欄位不足（非三大欄）→ 跳過
            if len(numstat_fields) < 3:
                continue
            # 二進位檔（-）→ 唔計
            if numstat_fields[0] == "-" or numstat_fields[1] == "-":
                continue
            additions_count += int(numstat_fields[0])
            deletions_count += int(numstat_fields[1])

        return {"additions": additions_count, "deletions": deletions_count}

    def collect_last_commit(self, project_path):
        """收集最後提交（log -1：sha／ISO 時間／標題；無提交回 None）。"""
        is_ok, stdout_text, _ = self.run_git_command(
            project_path, ["log", "-1", "--format=%h%x1f%cI%x1f%s"])
        if is_ok is False:
            return None

        commit_fields = stdout_text.strip(" \r\n").split("\x1f")
        # 欄位不足 → 視為無提交
        if len(commit_fields) < 3:
            return None

        last_commit = {}
        last_commit["sha"] = commit_fields[0]
        last_commit["committed_at"] = commit_fields[1]
        last_commit["subject"] = commit_fields[2]

        return last_commit

    def collect_recent_commits(self, project_path):
        """收集最近提交（log -N：sha／ISO 時間／標題；無提交回空清單）。"""
        is_ok, stdout_text, _ = self.run_git_command(
            project_path, ["log", "-" + str(RECENT_COMMIT_COUNT), "--format=%h%x1f%cI%x1f%s"])
        if is_ok is False:
            return []

        commit_list = []

        # 逐行解析（每個提交一行）
        for commit_line in stdout_text.splitlines():
            # 空行 → 跳過
            if commit_line.strip() == "":
                continue
            commit_fields = commit_line.split("\x1f")
            # 欄位不足 → 跳過
            if len(commit_fields) < 3:
                continue
            commit_list.append({
                "sha": commit_fields[0],
                "committed_at": commit_fields[1],
                "subject": commit_fields[2],
            })

        return commit_list

    def collect_remote_origin(self, project_path):
        """收集 origin 遠端 URL（無 origin 回空字串）。"""
        is_ok, stdout_text, _ = self.run_git_command(project_path, ["remote", "get-url", "origin"])
        if is_ok is False:
            return ""

        return stdout_text.strip()

    def get_status(self, project_path):
        # git 版本（未安裝 → 空字串 → 失敗）
        git_version = self.collect_git_version()
        if git_version == "":
            return {"ok": False, "error": "git not installed", "git_installed": False, "path_exists": os.path.isdir(project_path), "has_gitignore": os.path.isfile(os.path.join(project_path, ".gitignore"))}

        result = {}
        result["ok"] = True
        result["git_installed"] = True
        result["git_version"] = git_version
        result["path_exists"] = os.path.isdir(project_path)
        result["has_gitignore"] = os.path.isfile(os.path.join(project_path, ".gitignore"))
        result["is_repo"] = self.check_is_git_work_tree(project_path)

        # 非 git 工作樹 → 只回基本資訊（前端顯示「未初始化 Git」）
        if result["is_repo"] is False:
            return result

        changes = self.collect_changes(project_path)

        # status 讀取失敗 → 失敗（帶原因）
        if changes is None:
            return {"ok": False, "error": "git status failed"}

        head_sha = changes["head_sha"]
        has_commit = head_sha not in ("", "(initial)")

        result["branch"] = changes["branch"]
        result["detached"] = changes["detached"]
        result["head_sha"] = head_sha
        result["has_commit"] = has_commit
        result["upstream"] = changes["upstream"]
        result["ahead"] = changes["ahead"]
        result["behind"] = changes["behind"]
        result["changes"] = {
            "staged": changes["staged"],
            "unstaged": changes["unstaged"],
            "untracked": changes["untracked"],
            "conflicts": changes["conflicts"],
            "added": changes["added"],
            "deleted": changes["deleted"],
            "modified": changes["modified"],
            "renamed": changes["renamed"],
            "total": changes["total"],
        }
        # diff 行數：冇提交 = null；乾淨（total 0）= 直接 0/0；真有變動才 spawn numstat（省成本）
        if has_commit is False:
            result["diff"] = None
        elif changes["total"] == 0:
            result["diff"] = {"additions": 0, "deletions": 0}
        else:
            result["diff"] = self.collect_diff_summary(project_path)
        result["last_commit"] = self.collect_last_commit(project_path) if has_commit else None
        result["recent_commits"] = self.collect_recent_commits(project_path) if has_commit else []
        result["remote_origin"] = self.collect_remote_origin(project_path)

        return result

    def main(self):
        # stdout 強制 UTF-8（Windows 預設 cp950——PHP 側固定收 UTF-8 JSON）
        sys.stdout.reconfigure(encoding="utf-8")

        # 參數不足 → 用法錯誤
        if len(sys.argv) < 2:
            print(json.dumps({"ok": False, "error": "usage: git_status.py <project_path>"}))
            return

        project_path = sys.argv[1]

        # 路徑唔存在 → 失敗
        if not os.path.isdir(project_path):
            print(json.dumps({"ok": False, "error": "project path not found"}))
            return

        result = self.get_status(project_path)
        print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    git_status_class().main()

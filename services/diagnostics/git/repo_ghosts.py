"""專案 Git 幽靈檔清理（名單有、磁碟冇——"AD" 狀態）。

發問前預檢用：aider 建 repo-map 會遂個檢查名單內檔案；
幽靈（git 記錄話有、實際已冇）＝白行檢查＋逐個警告——
喺發送最前段清走，令 run 永遠由乾淨環境起步。

用法（bridge 路由呼叫——PHP 發問前預檢）：
    POST /diagnostics/git/clean_repo_ghosts {"project_path": "..."}

輸出（JSON）：
    {"ok": true, "cleaned": <清走條數>}   0＝本來就乾淨（最常見）
    {"ok": false, "error": "..."}        非 repo／git 錯誤（呼叫方 best-effort 唔理）

設計（對齊 git_status 原則）：
    git 動作一律在 Python 層執行（跨平台一致），PHP 只負責呼叫與讀 JSON；
    掃描用 --no-optional-locks（唯讀——不寫 .git/index）；
    清理用 --pathspec-from-file ＋ NUL 分隔（原始 bytes——中文／特殊字元路徑不失真）。
"""

import os
import subprocess
import tempfile

# 單一 git 命令逾時（秒）——大 repo／慢碟自我保護（對齊 git_status 設定）
GIT_TIMEOUT_SECONDS = 15


class repo_ghosts_class:
    def __init__(self):
        pass

    def clean_repo_ghosts(self, project_path):
        """清該專案嘅 git 幽靈檔（AD＝index 有、磁碟冇）——回傳 dict。"""
        is_ok, ghost_paths, error_text = self.scan_ghost_paths(project_path)

        # 判定：掃描失敗（非 repo／git 錯誤）→ 報失敗（呼叫方 best-effort 唔理）
        if is_ok is False:
            return {"ok": False, "error": error_text}

        # 判定：冇幽靈 → 原樣回 0（最常見——唔使動 git）
        if len(ghost_paths) == 0:
            return {"ok": True, "cleaned": 0}

        cleaned = self.remove_from_index(project_path, ghost_paths)

        # 有清到先記一行（零噪音——乾淨路徑零足跡）
        if cleaned > 0:
            from ..runtime import sc_logger
            sc_logger.log("repo ghosts cleaned: " + str(cleaned) + " (" + project_path + ")")

        return {"ok": True, "cleaned": cleaned}

    def scan_ghost_paths(self, project_path):
        """掃出 AD 條目（index 有、磁碟冇）——回傳 (是否成功, 路徑 bytes 列, 錯誤字串)。"""
        command = ["git", "-C", project_path, "--no-optional-locks", "status", "--porcelain", "-z"]

        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                timeout=GIT_TIMEOUT_SECONDS,
                check=False,
            )
        except (OSError, subprocess.SubprocessError) as run_error:
            return False, [], str(run_error)

        # 判定：非零退出碼 → 失敗（stderr 帶原因——多數＝路徑唔係 git 工作樹）
        if completed.returncode != 0:
            return False, [], completed.stderr.decode("utf-8", "replace").strip()

        # 解析（-z：條目以 NUL 分隔；原始 bytes——不經引號轉義）
        #   格式＝XY＋空格＋路徑；AD＝index 已加入、工作樹已刪
        entries = [row for row in completed.stdout.split(b"\0") if row]
        ghost_paths = [row[3:] for row in entries if row[:2] == b"AD"]

        return True, ghost_paths, ""

    def remove_from_index(self, project_path, ghost_paths):
        """將幽靈路徑逐個由 index 落架（永不動工作樹）——回傳清走條數。"""
        # 組 pathspec 檔（NUL 分隔——原始 bytes）
        with tempfile.NamedTemporaryFile(prefix="hkai_repo_ghosts_", suffix=".bin", delete=False) as spec_file:
            spec_path = spec_file.name
            for ghost_path in ghost_paths:
                spec_file.write(ghost_path + b"\0")

        try:
            completed = subprocess.run(
                [
                    "git", "-C", project_path,
                    "rm", "--cached", "-r", "--quiet", "--ignore-unmatch",
                    "--pathspec-from-file=" + spec_path,
                    "--pathspec-file-nul",
                ],
                capture_output=True,
                timeout=GIT_TIMEOUT_SECONDS,
                check=False,
            )
        except (OSError, subprocess.SubprocessError):
            return 0
        finally:
            try:
                os.remove(spec_path)
            except OSError:
                pass

        # 判定：非零退出碼 → 一條都當冇清
        if completed.returncode != 0:
            return 0

        return len(ghost_paths)

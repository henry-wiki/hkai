<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git render_git_status：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait render_git_status
{
    public function render_git_status($git_status)
    {
        // i18n 列標籤（單一來源——cls/i18n/lang/*.json）
        $row_status_label      = hkai__i18n()->get("git.row_status");
        $row_unstaged_label    = hkai__i18n()->get("git.row_unstaged");
        $row_staged_label      = hkai__i18n()->get("git.row_staged");
        $row_branch_label      = hkai__i18n()->get("git.row_branch");
        $row_last_commit_label = hkai__i18n()->get("git.row_last_commit");
        $read_failed_label     = hkai__i18n()->get("git.read_failed");
        $not_initialized_text  = hkai__i18n()->get("git.row_not_initialized");
        $recent_commits_label  = hkai__i18n()->get("git.recent_commits");
        $stage_all_label       = hkai__i18n()->get("git.stage_all");
        $commit_label          = hkai__i18n()->get("git.commit");
        $unit_files_label      = hkai__i18n()->get("git.unit_files");
        $unit_conflicts_label  = hkai__i18n()->get("git.unit_conflicts");

        $outputLines   = [];
        $outputLines[] = "<div class='git_pane'>";

        // 判定：診斷失敗 → 單項未通過（重新整理走 modal footer 共用鈕）
        if ($git_status["ok"] !== true) {
            $outputLines[] = "<div class='git_summary'>";
                $outputLines[] = $this->render_git_check_item($row_status_label, $read_failed_label . $git_status["error"], false);
            $outputLines[] = "</div>";
            $outputLines[] = "</div>";
            return implode("\r\n", $outputLines);
        }

        // 判定：非 git 工作樹 → 未初始化（init 掣淨喺診斷頁）
        if ($git_status["is_repo"] !== true) {
            $outputLines[] = "<div class='git_summary'>";
                $outputLines[] = $this->render_git_check_item($row_status_label, $not_initialized_text, false);
            $outputLines[] = "</div>";
            $outputLines[] = "</div>";
            return implode("\r\n", $outputLines);
        }

        // 分支（detached → HEAD@sha7）
        $git_branch = $this->get_git_branch_text($git_status);

        $outputLines[] = $this->get_git_headline($git_branch, $git_status);

        // 兩步模型（未準備＝unstaged＋untracked——Stage 前；已準備＝staged——Commit 前）
        $git_changes         = $git_status["changes"];
        $git_stageable_count = $git_changes["unstaged"] + $git_changes["untracked"];
        $git_staged_count    = $git_changes["staged"];

        // 未準備文字（n 個檔案）
        $git_unstaged_text = $git_stageable_count . " " . $unit_files_label;

        // 判定：有衝突 → 未準備文字附衝突標示
        if ($git_changes["conflicts"] > 0) {
            $git_unstaged_text .= " ⚠ " . $git_changes["conflicts"] . " " . $unit_conflicts_label;
        }

        // 已準備文字（n 個檔案）
        $git_staged_text = $git_staged_count . " " . $unit_files_label;

        // 動作掣（未準備→Stage；已準備→Commit——各自夠數先出；右側）
        $stage_action_html  = "";
        $commit_action_html = "";

        // 判定：有未準備 → Stage 掣（git add -A）
        if ($git_stageable_count > 0) {
            $stage_action_html = "<button type='button' class='act' data-btn='git_stage_all'>" . $stage_all_label . "</button>";
        }

        // 判定：有已準備 → Commit 掣（git commit）
        if ($git_staged_count > 0) {
            $commit_action_html = "<button type='button' class='act' data-btn='git_commit'>" . $commit_label . "</button>";
        }

        // 通過判斷（未準備——冇嘢待 Stage）
        $unstaged_passed = true;

        // 判定：有未準備變更 → 未通過
        if ($git_stageable_count > 0) {
            $unstaged_passed = false;
        }

        // 通過判斷（已準備——冇嘢待 Commit）
        $staged_passed = true;

        // 判定：有已準備變更 → 未通過
        if ($git_staged_count > 0) {
            $staged_passed = false;
        }

        // 群組標題（分支 : <當前分支>）
        $branch_group_text = $row_branch_label . " : " . $git_branch;

        $outputLines[] = "<div class='git_summary'>";
            $outputLines[] = "<div class='check_group'>" . sc_html_encode($branch_group_text, ENT_QUOTES) . "</div>";
            $outputLines[] = $this->render_git_check_item($row_unstaged_label, $git_unstaged_text, $unstaged_passed, $stage_action_html);
            $outputLines[] = $this->render_git_check_item($row_staged_label, $git_staged_text, $staged_passed, $commit_action_html);

            // 純文字頁腳（最後提交／Git 版本——兩行）
            $outputLines[] = "<div class='git_footer'>";
                $outputLines[] = "<div>" . $row_last_commit_label . " : " . sc_html_encode($this->get_git_last_commit_text($git_status), ENT_QUOTES) . "</div>";
                $outputLines[] = "<div>" . sc_html_encode($git_status["git_version"], ENT_QUOTES) . "</div>";
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        // 判定：有最近提交 → 輸出清單
        if (count($git_status["recent_commits"]) > 0) {
            $outputLines[] = "<div class='check_group'>" . $recent_commits_label . "</div>";
            $outputLines[] = "<div class='git_commits'>";

            // 逐筆提交（sha ＋ 主旨 ＋ 時間）
            foreach ($git_status["recent_commits"] as $commit_row) {
                $outputLines[] = $this->get_git_commit($commit_row);
            }

            $outputLines[] = "</div>";
        }

        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}

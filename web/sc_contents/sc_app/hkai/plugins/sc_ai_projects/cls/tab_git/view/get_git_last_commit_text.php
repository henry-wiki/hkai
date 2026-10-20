<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_last_commit_text：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_last_commit_text
{
    private function get_git_last_commit_text($git_status)
    {
        $commit_text = hkai__i18n()->get("git.no_commits");

        // 判定：有提交 → 組顯示文字
        if ($git_status["last_commit"] !== null) {
            $git_commit  = $git_status["last_commit"];
            $commit_time = date("Y-m-d H:i", strtotime($git_commit["committed_at"]));
            $commit_text = $git_commit["sha"] . " · " . $commit_time . " · " . $git_commit["subject"];
        }

        return $commit_text;
    }
}

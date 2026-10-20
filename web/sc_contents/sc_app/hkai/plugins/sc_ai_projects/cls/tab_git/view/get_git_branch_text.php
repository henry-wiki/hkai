<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_branch_text：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_branch_text
{
    private function get_git_branch_text($git_status)
    {
        $git_branch = $git_status["branch"];

        // 判定：detached HEAD → 以短 sha 顯示
        if ($git_status["detached"] === true) {
            $git_branch = "HEAD@" . substr($git_status["head_sha"], 0, 7);
        }

        // 判定：無分支名 → 顯示佔位
        if ($git_branch === "") {
            $git_branch = hkai__i18n()->get("git.no_branch");
        }

        return $git_branch;
    }
}

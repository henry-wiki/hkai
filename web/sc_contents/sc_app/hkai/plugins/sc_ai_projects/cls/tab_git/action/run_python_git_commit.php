<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\action;

// == sc_ai_projects tab_git run_python_git_commit：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==

trait run_python_git_commit
{
    private function run_python_git_commit($project_path)
    {
        // 大批量 commit 可以慢——總時限放寬到 180 秒
        return hkai__python_bridge()->request("/projects/git/commit/", [
            "project_path" => $project_path,
        ], 180);
    }
}

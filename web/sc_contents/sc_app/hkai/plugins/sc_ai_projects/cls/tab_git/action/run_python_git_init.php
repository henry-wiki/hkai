<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\action;

// == sc_ai_projects tab_git run_python_git_init：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/handler.php 分遷入（one file one action） ==

trait run_python_git_init
{
    private function run_python_git_init($project_path)
    {
        return hkai__python_bridge()->request("/projects/git/init/", [
            "project_path" => $project_path,
        ]);
    }
}

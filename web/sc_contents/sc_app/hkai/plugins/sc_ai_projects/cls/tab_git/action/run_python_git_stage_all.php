<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\action;

// == sc_ai_projects tab_git run_python_git_stage_all：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==

trait run_python_git_stage_all
{
    private function run_python_git_stage_all($project_path)
    {
        // 首次批量 add 好慢（實測 240 檔＝36 秒）——總時限放寬到 180 秒
        return hkai__python_bridge()->request("/projects/git/stage_all/", [
            "project_path" => $project_path,
        ], 180);
    }
}

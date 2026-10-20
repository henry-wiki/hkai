<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\git\status;

// == sc_ai_diagnostics git run_git_status：git 域動作（trait——掛入 git_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat git_Class 分遷入（one file one action） ==

trait run_git_status
{
    // 執行 Git 狀態診斷（HTTP bridge——Python 診斷程式；PHP 只負責呼叫與讀 JSON）
    public function run_git_status($project_path)
    {
        return hkai__python_bridge()->request("/diagnostics/git/get_status", ["project_path" => $project_path]);
    }
}

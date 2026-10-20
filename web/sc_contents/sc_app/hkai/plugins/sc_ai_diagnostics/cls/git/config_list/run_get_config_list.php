<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\git\config_list;

// == sc_ai_diagnostics git run_get_config_list：git 域動作（trait——掛入 git_Class；檔＝動作） ==

trait run_get_config_list
{
    // 執行 Git 設定一覽（HTTP bridge——Python 診斷程式；PHP 只負責呼叫與讀 JSON）
    public function run_get_config_list($project_path)
    {
        return hkai__python_bridge()->request("/diagnostics/git/get_config_list", ["project_path" => $project_path]);
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\files\long_paths;

// == sc_ai_diagnostics files run_get_long_paths：files 域動作（trait——掛入 files_Class；檔＝動作） ==

trait run_get_long_paths
{
    // 執行檔案路徑長度掃描（HTTP bridge——Python 掃描程式；PHP 只負責呼叫與讀 JSON）
    public function run_get_long_paths($project_path)
    {
        return hkai__python_bridge()->request("/diagnostics/files/get_long_paths", ["project_path" => $project_path]);
    }
}

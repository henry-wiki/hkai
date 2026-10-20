<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\project_env\action;

// == sc_ai_diagnostics project_env run_project_env_fetch：project_env 域動作（trait——掛入 project_env_Class；檔＝動作） ==
// == 職責：向 Python 索要專案級環境資料（HTTP bridge——帶專案路徑；PHP 只負責呼叫與讀 JSON） ==

trait run_project_env_fetch
{
    // 向 bridge 索要專案級環境（回傳統一結果：ok=true 時自同一份 data 帶環境欄位／ok=false 帶 error）
    public function run_project_env_fetch($project_path)
    {
        return hkai__python_bridge()->request("/diagnostics/project_env/get", ["project_path" => $project_path]);
    }
}

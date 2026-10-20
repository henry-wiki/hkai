<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\system_env\action;

// == sc_ai_diagnostics system_env run_system_env_fetch：system_env 域動作（trait——掛入 system_env_Class；檔＝動作） ==
// == 職責：向 Python 索要系統級環境資料（HTTP bridge——只讀診斷；PHP 只負責呼叫與讀 JSON） ==

trait run_system_env_fetch
{
    // 向 bridge 索要系統級環境（回傳統一結果：ok=true 時自同一份 data 帶環境欄位／ok=false 帶 error）
    public function run_system_env_fetch()
    {
        return hkai__python_bridge()->request("/diagnostics/system_env/get");
    }
}

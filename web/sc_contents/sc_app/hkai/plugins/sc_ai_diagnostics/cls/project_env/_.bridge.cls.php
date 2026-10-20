<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\project_env;

// == sc_ai_diagnostics project_env：project_env 域本體（bridge 檔——singleton；呼叫形 sc_ai_diagnostics()->project_env()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：專案級環境偵測資料的索要（Python 偵測（帶專案路徑） → PHP 讀 JSON） ==

class project_env_Class
{
    use action\run_project_env_fetch;

    private static $instance = null;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;

            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {}
}

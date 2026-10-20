<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\files;

// == sc_ai_diagnostics files：files 域本體（bridge 檔——singleton；呼叫形 sc_ai_diagnostics()->files()） ==
// == 動作（trait——一檔一動作；2026-10-03 立（檔案隱患掃描——首項：路徑長度）） ==

class files_Class
{
    use long_paths\run_get_long_paths;

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

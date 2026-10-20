<?php

namespace sc\app\hkai\plugins\sc_ai_diagnostics\git;

// == sc_ai_diagnostics git：git 域本體（bridge 檔——singleton；呼叫形 sc_ai_diagnostics()->git()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat git_Class 合成） ==

class git_Class
{
    use config_list\run_get_config_list;
    use repo_ghosts\run_clean_repo_ghosts;
    use status\get_git_status_item;
    use status\run_git_status;

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

<?php

namespace sc\app\hkai\plugins\sc_ai_projects\health;

// == sc_ai_projects health\health_Class（bridge 檔）：健康報告域本體（畀 sc_ai_health 取數——sc_ai_projects()->health()） ==
// == 動作（cls/health/ 各 trait——一檔一動作）：get_project_report ==

class health_Class
{
    use get_project_report;

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
    {
    }

}

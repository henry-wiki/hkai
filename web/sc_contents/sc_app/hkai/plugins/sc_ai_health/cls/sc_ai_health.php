<?php

namespace sc\app\hkai\plugins\sc_ai_health;

// == sc_ai_health_Class：插件頂層本體（singleton——bridging sc_ai_health()） ==
// == 職責：域入口（accessor）——例：sc_ai_health()->health()->fetch_health() ==
// == 2026-09-27 域收攏（舊 2 支 sc_ai_health__<x>() bridging 收成頂層單一入口） ==

class sc_ai_health_Class
{

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

    /**
     * health 域（health_Class——config／data）
     *
     * @return \sc\app\hkai\plugins\sc_ai_health\health\health_Class
     */
    public function health()
    {
        return health\health_Class::getInstance();
    }

    /**
     * heartbeat 域（heartbeat_Class——config／beat）
     *
     * @return \sc\app\hkai\plugins\sc_ai_health\heartbeat\heartbeat_Class
     */
    public function heartbeat()
    {
        return heartbeat\heartbeat_Class::getInstance();
    }
}

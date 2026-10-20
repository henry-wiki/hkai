<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar;

// == sc_ai_status_bar_Class：插件頂層本體（singleton——bridging sc_ai_status_bar()） ==
// == 職責：域入口（accessor）——例：sc_ai_status_bar()->status_bar()->render_region("workspace") ==
// == 2026-09-27 域收攏（舊 sc_ai_status_bar()->status_bar() 收成頂層單一入口） ==

class sc_ai_status_bar_Class
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
     * status_bar 域（status_bar_Class——item／state／render）
     *
     * @return \sc\app\hkai\plugins\sc_ai_status_bar\status_bar\status_bar_Class
     */
    public function status_bar()
    {
        return status_bar\status_bar_Class::getInstance();
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_health\request;

// == request_Class：request 域本體（request.php——singleton；呼叫形 sc_ai_health()->request()->…） ==
// == 職責：request 動作（trait——一檔一動作：fetch_health／health_stream） ==

class request_Class
{
    use fetch_health;
    use health_stream;

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

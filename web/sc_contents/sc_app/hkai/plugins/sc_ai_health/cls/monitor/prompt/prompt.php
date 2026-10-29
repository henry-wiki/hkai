<?php

namespace sc\app\hkai\plugins\sc_ai_health\monitor\prompt;

// == prompt_Class：prompt 監視對象（singleton——只做組裝） ==
// == 上游：monitor_Class 清單；下游：fetch_entries＋compare_entries（同 namespace） ==

class prompt_Class
{
    use fetch_entries;
    use compare_entries;

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

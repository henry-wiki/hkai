<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\log;

// == sc_ai_chats poll\log 子域：bridge 檔——只放 class（.cls.php＝class 專用；.func.php＝function 專用） ==
// == 載入：autoloader（一般映射先行、本目錄 bridge 檔後備——_.bridge.cls.php 系統指定名） ==
// == 唯一入口：sc_ai_chats()->poll()->log()——log 方法唔喺 poll_Class 上，直接叫唔到 ==

class log_Class
{
    use append_log;
    use get_token_sums_from_stream_log;
    use parse_activity_events;
    use build_invalid_log_entry;

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

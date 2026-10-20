<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\request;

// == sc_ai_chats poll\request request_Class：poll request 子域本體（singleton——sc_ai_chats()->poll()->request()） ==
// == 動作（本目錄各 trait——一檔一動作）：handle_message_poll／handle_queue_cancel／handle_queue_dispatch／handle_queue_clear ==
// == 2026-09-27 建（request 相關動作自 cls/poll/ 收攏入子域） ==

// == 2026-09-27 本體改用本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class request_Class
{
    use handle_message_poll;
    use handle_queue_cancel;
    use handle_queue_dispatch;
    use handle_queue_clear;

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

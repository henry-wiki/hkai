<?php

namespace sc\app\hkai\plugins\sc_ai_chats\process;

// == sc_ai_chats process\process_Class（bridge 檔）：process 域本體（singleton——bridging sc_ai_chats()->process()） ==
// == 動作（cls/process/ 各 trait——一檔一動作）：kill_message_children／find_worker_pids ==
// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class process_Class
{
    use kill_message_children;
    use find_worker_pids;

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

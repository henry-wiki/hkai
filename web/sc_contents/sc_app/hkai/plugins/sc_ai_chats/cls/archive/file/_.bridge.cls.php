<?php

namespace sc\app\hkai\plugins\sc_ai_chats\archive\file;

// == sc_ai_chats archive\file\file_Class（bridge 檔）：archive 讀取子域（單例——sc_ai_chats()->archive()->file()） ==
// == 職責：歸檔後讀取（狀態）——一檔一動作（cls/archive/file/ 各 trait） ==

class file_Class
{
    use read_archived_message_status;

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

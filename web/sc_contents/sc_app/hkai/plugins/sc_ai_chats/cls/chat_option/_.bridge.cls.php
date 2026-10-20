<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat_option;

// == sc_ai_chats chat_option\chat_option_Class（bridge 檔）：對話選項域本體（singleton——bridging sc_ai_chats()->chat_option()） ==
// == 動作（cls/chat_option/ 各 trait——一檔一動作）：get_chat_options／get_chat_mode ==
// == 2026-10-02 自 sc_ai_providers（main／request 域）遷入——對話選項歸 chats（同 jsmods/chat_option 對稱） ==

class chat_option_Class
{
    use get_chat_options;
    use get_chat_mode;

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

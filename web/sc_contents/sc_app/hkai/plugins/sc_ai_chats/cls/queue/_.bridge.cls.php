<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue;

// == sc_ai_chats queue\queue_Class（bridge 檔）：queue 域本體（singleton——bridging sc_ai_chats()->queue()） ==
// == 動作（cls/queue/ 各 trait——一檔一動作）：get_queue_file_path／append_queue_line／get_queue_lines／remove_queue_line／shift_queue_message／delete_queue_file ==
// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 後備載入） ==

// == 2026-09-27 本體自 cls 根上移入本目錄 bridge（_.bridge.cls.php——系統指定名；autoloader 優先載入） ==
class queue_Class
{
    use file\append_queue_line;
    use file\get_queue_lines;
    use file\remove_queue_line;
    use action\shift_queue_message;
    use file\delete_queue_file;
    use action\materialize_queue_message;

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


    //---------------------------------------------------------
    // 1. 回排隊檔路徑（命名＝queue_{chat_sid}.jsonl）
    //---------------------------------------------------------
    public function get_queue_file_path($chat_id)
    {
        $chat_row = sc_ai_chats()->chat()->get_chat_row($chat_id);
        $chat_sid = $chat_row["chat_sid"];

        return sc_ai_chats()->storage()->get_messages_dir() . "/queue_" . $chat_sid . ".jsonl";
    }

}

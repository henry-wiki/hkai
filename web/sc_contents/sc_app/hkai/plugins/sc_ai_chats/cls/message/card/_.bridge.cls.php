<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\card;

// == sc_ai_chats message\card（bridge 檔）：訊息卡（單一檔案——message 1 row ＋ responses N row） ==
// == 2026-10-19 新制：串流期間只寫卡檔（快）；終態 finalize 一次 flush 入 SQLite ==
// == 結構 100% 對照 SQLite：messages 表 1 row ＋ message_response 表 N row（欄位同名同序） ==

class card_Class
{
    use get_message_card_path;
    use read_message_card;
    use write_message_card;
    use allocate_response_id;
    use upsert_message_card_response;
    use update_message_card_status;
    use flush_message_card;
    use delete_message_card;

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

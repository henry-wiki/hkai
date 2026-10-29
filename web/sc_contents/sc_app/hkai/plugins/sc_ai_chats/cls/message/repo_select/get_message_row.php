<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_select;

// == sc_ai_chats message repo_select get_message_row：repo_select 子域動作（trait——合成入 repo_select_Class；檔＝動作：單條訊息列讀取） ==
// == 2026-10-29 取消卡：一律讀 DB（段由 poll 檔每 tick upsert） ==

trait get_message_row
{
    // 讀取單一訊息列（依 message_id——DB 單一來源）
    public function get_message_row($message_id)
    {
        return sc_db()->connection("sqlite")->selectOne(
            "SELECT * FROM messages WHERE message_id = ?",
            [$message_id]
        );
    }

}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_select;

// == sc_ai_chats message repo_select get_message_row：repo_select 子域動作（trait——合成入 repo_select_Class；檔＝動作：單條訊息列讀取） ==
// == 2026-10-19 卡優先：串流期間讀卡檔（message row 同形），冇卡＝讀 DB ==

trait get_message_row
{
    // 讀取單一訊息列（依 message_id——卡優先、DB 其次）
    public function get_message_row($message_id)
    {
        $message_card = sc_ai_chats()->message()->card()->read_message_card($message_id);

        // 判定：卡存在（串流中）→ 回卡內 message row
        if ($message_card !== null) {
            return $message_card["message"];
        }

        return sc_db()->connection("sqlite")->selectOne(
            "SELECT * FROM messages WHERE message_id = ?",
            [$message_id]
        );
    }

}

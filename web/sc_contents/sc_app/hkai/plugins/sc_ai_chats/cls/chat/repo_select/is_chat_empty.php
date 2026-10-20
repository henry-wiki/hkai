<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_select;

// == sc_ai_chats chat is_chat_empty：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 message/action.php 分獨立檔；2026-09-27 歸 chat 域（讀 chats 表 chat_message_count） ==

trait is_chat_empty
{
    //---------------------------------------------------------
    // 1. 睇仲有冇訊息剩
    //---------------------------------------------------------
    public function is_chat_empty($chat_id)
    {
        $chat_row = sc_ai_chats()->chat()->get_chat_row($chat_id);

        // 判定：對話列已不存在 → 不重複處理
        if ($chat_row === null) {
            return false;
        }

        return ((int)$chat_row["chat_message_count"] === 0);
    }

}

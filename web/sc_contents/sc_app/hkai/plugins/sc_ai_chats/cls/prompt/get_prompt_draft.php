<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt;

// == sc_ai_chats prompt get_prompt_draft：域動作（trait——掛入 prompt_Class；檔＝動作：活動 prompt_draft 讀取（草稿整包）） ==

trait get_prompt_draft
{

    // 讀取活動 prompt_draft 草稿整包（以活動列 scope〔chat_id〕對當前對話——無記錄／草稿屬其他對話＝null；查無回 null、有 → bundle）
    public function get_prompt_draft($chat_sid)
    {
        // 當前對話 id（chat_sid 解 chat row——同活動列 scope〔chat_id〕對照用）
        $chat_row = sc_ai_chats()->chat()->get_chat_row_by_sid($chat_sid);
        $chat_id  = $chat_row ? (int)$chat_row["chat_id"] : 0;

        // 讀活動（scope 篩選——只有當前對話嗰條；無／唔屬＝null）
        $prompt_draft_bundle = hkai__sc_page_activity()->get_activity("prompt_draft", ["chat_id" => $chat_id]);

        return $prompt_draft_bundle;
    }
}

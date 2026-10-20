<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\file;

// == sc_ai_chats prompt set_polling_msg_user_prompt：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 2026-09-27 自 poll/prompt.php 分獨立檔；2026-09-27 歸 prompt 域（polling 訊息嘅 prompt 檔讀寫） ==

trait set_polling_msg_user_prompt
{

    // 寫入 msg_prompt（JSON——問題內容 ＋ token 統計）
    // input_token：由子程序計算後寫回（問題本身的 tokens）；complete_token：終態時填入（主回覆的 completion_tokens）
    public function set_polling_msg_user_prompt($chat_id, $message_id, $user_prompt_content)
    {
        $prompt_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "prompt");

        $prompt_data = [];
        $prompt_data["content"]        = $user_prompt_content;
        $prompt_data["input_token"]    = 0;
        $prompt_data["complete_token"] = 0;

        sc_write_json($prompt_file, $prompt_data);
    }

}

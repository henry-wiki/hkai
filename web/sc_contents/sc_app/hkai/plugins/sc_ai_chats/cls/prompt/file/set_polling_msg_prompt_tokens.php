<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\file;

// == sc_ai_chats prompt set_polling_msg_prompt_tokens：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 2026-09-27 自 poll/prompt.php 分獨立檔；2026-09-27 歸 prompt 域（polling 訊息嘅 prompt 檔讀寫） ==

trait set_polling_msg_prompt_tokens
{

    // 更新 prompt 檔的 token 統計（input_token／complete_token；null = 不改該欄）
    public function set_polling_msg_prompt_tokens($chat_id, $message_id, $input_token = null, $complete_token = null)
    {
        $prompt_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "prompt");

        // 狀態判斷：prompt 檔已唔存在（訊息已歸檔移走——poll／flush 同時收尾時，後到一方嘅寫入會撲空）→ 無嘢可更新
        if (!is_file($prompt_file)) {
            return;
        }

        $prompt_data = sc_ai_chats()->prompt()->parse_polling_prompt_file($prompt_file);

        // 判定：有給 input_token → 更新
        if ($input_token !== null) {
            $prompt_data["input_token"] = (int)$input_token;
        }
        // 判定：有給 complete_token → 更新
        if ($complete_token !== null) {
            $prompt_data["complete_token"] = (int)$complete_token;
        }

        sc_write_json($prompt_file, $prompt_data);
    }

}

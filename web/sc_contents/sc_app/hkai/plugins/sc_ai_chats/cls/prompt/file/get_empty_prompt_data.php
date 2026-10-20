<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\file;

// == sc_ai_chats prompt get_empty_prompt_data：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 2026-09-27 自 poll/prompt.php 分獨立檔；2026-09-27 歸 prompt 域（polling 訊息嘅 prompt 檔讀寫） ==

trait get_empty_prompt_data
{
    // 空 prompt 結構（解析失敗時的預設值）
    private function get_empty_prompt_data()
    {
        $prompt_data = [];
        $prompt_data["content"]        = "";
        $prompt_data["input_token"]    = 0;
        $prompt_data["complete_token"] = 0;

        return $prompt_data;
    }

}

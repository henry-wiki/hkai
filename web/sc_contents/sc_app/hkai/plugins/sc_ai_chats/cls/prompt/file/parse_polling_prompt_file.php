<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\file;

// == sc_ai_chats prompt parse_polling_prompt_file：prompt 域動作（trait——掛入 prompt_Class；檔＝動作） ==
// == 2026-09-27 自 poll/prompt.php 分獨立檔；2026-09-27 歸 prompt 域（polling 訊息嘅 prompt 檔讀寫） ==

trait parse_polling_prompt_file
{

    // 解析 prompt 檔（JSON → 陣列；缺失／格式錯 → 預設空結構）
    public function parse_polling_prompt_file($file_path)
    {
        $prompt_raw = @file_get_contents($file_path);

        // 判定：讀不到 → 回預設結構
        if ($prompt_raw === false) {
            return sc_ai_chats()->prompt()->get_empty_prompt_data();
        }

        $prompt_data = json_decode($prompt_raw, true);

        // 判定：解析失敗 → 回預設結構
        if (!is_array($prompt_data)) {
            return sc_ai_chats()->prompt()->get_empty_prompt_data();
        }

        return $prompt_data;
    }

}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat get_bare_chat_sid：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-10-04 純 chat_sid 抽取（語境形 keyword@<chat_sid> → @ 後；無 @ → 原值——第一個 @ 分界） ==

trait get_bare_chat_sid
{

    // 由對話段取純 chat_sid（查表／操作一律用純 sid；語境由語境判定函數負責）
    public function get_bare_chat_sid($chat_segment)
    {
        // 無 @ → 純 sid 原值
        if (strpos($chat_segment, "@") === false) {
            return $chat_segment;
        }

        // 語境形 → @ 後（第一個 @ 分界）
        return substr($chat_segment, strpos($chat_segment, "@") + 1);
    }

}

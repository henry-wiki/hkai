<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat is_archive_chat_segment：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-10-04 對話段歸檔語境判定（archive＝歸檔清單／archive@<chat_sid>＝歸檔語境瀏覽——第一個 @ 分界） ==

trait is_archive_chat_segment
{

    // 判定對話段係唔係歸檔語境（純關鍵字 archive，或語境形 archive@<chat_sid>；解析規則唯一住處）
    public function is_archive_chat_segment($chat_segment)
    {
        // 未指定（null——caller 冇帶 chat_sid）＝唔係歸檔語境
        // 2026-10-14 測試發現：PHP 8.1+ strpos(null) 已 deprecate——會污染 JSON 輸出
        if ($chat_segment === null) {
            return false;
        }

        // 純關鍵字（歸檔清單本身——/archive/）
        if ($chat_segment === "archive") {
            return true;
        }

        // 判定：語境形（archive@<chat_sid>——前＝關鍵字、後＝對話；第一個 @ 分界）
        if (strpos($chat_segment, "archive@") === 0) {
            return true;
        }

        return false;
    }

}

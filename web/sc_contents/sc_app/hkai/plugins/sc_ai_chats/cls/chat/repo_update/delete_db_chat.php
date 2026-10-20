<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_update;

// == sc_ai_chats chat delete_db_chat：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/repo_update.php 分獨立檔（one file one function） ==

trait delete_db_chat
{
    // 硬刪除對話（chats 列 ＋ 殘留 messages／message_response 列——同一事務）
    // 檔案刪除由 history_Class 負責（sc_ai_chats()->chat()——chat_history_delete）——本方法只碰 DB
    public function delete_db_chat($chat_id)
    {
        $connection = sc_db()->connection("sqlite");

        $connection->transaction(function ($db) use ($chat_id) {
            // → 殘留關聯列（正常流程已隨訊息清空——保險清，避免孤兒列）
            $db->statement("DELETE FROM message_response WHERE chat_id = ?", [$chat_id]);
            $db->statement("DELETE FROM messages WHERE chat_id = ?", [$chat_id]);

            // → 對話列
            $db->statement("DELETE FROM chats WHERE chat_id = ?", [$chat_id]);
        });
    }

}

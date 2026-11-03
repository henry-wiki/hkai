<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_update;

// == sc_ai_chats chat delete_db_chat：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_chats/cls/chat/repo_update/ 分獨立檔（one file one function） ==

trait delete_db_chat
{
    // 硬刪除對話（chats 列 ＋ 殘留 messages／message_response 列——同一事務）
    // 本方法只碰 DB（history 功能已於 2026-10-29 移除）
    public function delete_db_chat($chat_id)
    {
        $connection = sc_db()->connection("sqlite");

        $connection->transaction(function ($db) use ($chat_id) {
            // → 殘留關聯列（正常流程已隨訊息清空——保險清，避免孤兒列；附件列先行）
            $db->statement("DELETE FROM attachments WHERE chat_id = ?", [$chat_id]);
            $db->statement("DELETE FROM message_response WHERE chat_id = ?", [$chat_id]);
            $db->statement("DELETE FROM messages WHERE chat_id = ?", [$chat_id]);

            // → 對話列
            $db->statement("DELETE FROM chats WHERE chat_id = ?", [$chat_id]);
        });

        // 對話刪除 → 通知監視（chat／project gate）
    }

}

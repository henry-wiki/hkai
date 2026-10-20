<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update delete_db_message：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 message/repo_update.php 分獨立檔（one file one function） ==

trait delete_db_message
{
    // 刪除 DB：整則訊息（message_response 列 ＋ messages 列 ＋ 重算 chat count——同一事務，中途失敗整體 rollback）
    public function delete_db_message($message_id, $chat_id)
    {
        $current_time = date("Y-m-d H:i:s");

        $connection = sc_db()->connection("sqlite");
        $connection->transaction(function ($db) use ($message_id, $chat_id, $current_time) {
            $db->statement("DELETE FROM message_response WHERE message_id = ?", [$message_id]);
            $db->statement("DELETE FROM messages WHERE message_id = ?", [$message_id]);

            // 全量重算（五欄——刪嘅訊息屬邊個桶自動跟返；同 update_db_message_status 嘅 delta 互補）
            $count_msg       = "SELECT COUNT(*) FROM messages WHERE chat_id = " . (int)$chat_id;
            $count_completed = "SELECT COUNT(*) FROM messages WHERE chat_id = " . (int)$chat_id . " AND message_status = 'completed'";
            $count_failed    = "SELECT COUNT(*) FROM messages WHERE chat_id = " . (int)$chat_id . " AND message_status = 'failed'";
            $count_cancelled = "SELECT COUNT(*) FROM messages WHERE chat_id = " . (int)$chat_id . " AND message_status = 'cancelled'";
            $count_polling   = "SELECT COUNT(*) FROM messages WHERE chat_id = " . (int)$chat_id . " AND message_status NOT IN ('completed', 'failed', 'cancelled')";

            $db->statement(
                "UPDATE chats SET chat_message_count = (" . $count_msg . "), completed_msg = (" . $count_completed . "), failed_msg = (" . $count_failed . "), cancelled_msg = (" . $count_cancelled . "), polling_msg = (" . $count_polling . "), chat_updated_at = ? WHERE chat_id = ?",
                [$current_time, $chat_id]
            );
        });
    }

}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment delete_by_message：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：刪訊息時一併刪附件（DB 列＋磁盤檔——用戶要求：刪除必須真刪） ==

trait delete_by_message
{
    // 刪除（按 message_id——先刪檔再刪列）
    public function delete_by_message($message_id)
    {
        $connection = sc_db()->connection("sqlite");

        $attachment_rows = $connection->select(
            "SELECT attachment_id, filepath FROM attachments WHERE message_id = ?",
            [(int)$message_id]
        );

        // 判定：冇附件 → 唔動作
        if (count($attachment_rows) < 1) {
            return;
        }

        $storage_dir = sc_ai_chats()->storage()->get_storage_dir();

        // 逐個刪檔（檔案唔存在＝略過）
        foreach ($attachment_rows as $attachment_row) {
            $absolute_filepath = $storage_dir . "/" . $attachment_row["filepath"];

            // 判定：檔案存在 → 刪除
            if (is_file($absolute_filepath)) {
                @unlink($absolute_filepath);
            }
        }

        $connection->statement("DELETE FROM attachments WHERE message_id = ?", [(int)$message_id]);
    }
}

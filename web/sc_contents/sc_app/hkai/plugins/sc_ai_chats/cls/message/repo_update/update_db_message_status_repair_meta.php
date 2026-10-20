<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message repo_update update_db_message_status_repair_meta：repo_update 子域動作（trait——合成入 repo_update_Class；檔＝動作） ==
// == 2026-09-27 自 message/repo_update.php 分獨立檔（one file one function） ==

trait update_db_message_status_repair_meta
{
    // 寫入 DB：狀態修復記錄（message_meta 併寫——對賬審計：from／to／reason／時間）
    public function update_db_message_status_repair_meta($message_id, $repair_record)
    {
        $connection = sc_db()->connection("sqlite");

        $message_row = $connection->selectOne("SELECT message_meta FROM messages WHERE message_id = ?", [$message_id]);

        $meta_data = [];
        // 判定：已有 meta → 解出嚟併寫（唔覆蓋原有 key）
        if ($message_row !== null) {
            $decoded_meta = json_decode((string)$message_row["message_meta"], true);
            // 判定：解得成 array → 採用
            if (is_array($decoded_meta)) {
                $meta_data = $decoded_meta;
            }
        }

        $meta_data["status_repair"] = $repair_record;

        $connection->statement(
            "UPDATE messages SET message_meta = ? WHERE message_id = ?",
            [sc_json_encode($meta_data), $message_id]
        );
    }

}

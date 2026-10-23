<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment bind_to_message：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：訊息落地後綁定附件（message_id／chat_id；只綁未綁定嗰批） ==

trait bind_to_message
{
    // 綁定（shift 時刻呼叫——message_id 已出）
    public function bind_to_message($project_id, $attachment_ids, $chat_id, $message_id)
    {
        // 判定：冇附件 → 唔動作
        if (count($attachment_ids) < 1) {
            return;
        }

        $placeholders = [];
        $bindings     = [];
        $bindings[]   = (int)$chat_id;
        $bindings[]   = (int)$message_id;
        $bindings[]   = date("Y-m-d H:i:s");
        $bindings[]   = (int)$project_id;

        // 砌 IN 佔位（id 一律轉 int）
        foreach ($attachment_ids as $attachment_id_item) {
            $placeholders[] = "?";
            $bindings[]     = (int)$attachment_id_item;
        }

        $sql = "UPDATE attachments SET chat_id = ?, message_id = ?, attachment_updated_at = ?"
            . " WHERE project_id = ? AND message_id = 0 AND attachment_id IN (" . implode(",", $placeholders) . ")";

        $connection = sc_db()->connection("sqlite");
        $connection->statement($sql, $bindings);
    }
}

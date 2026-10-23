<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment get_rows_by_ids：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：按 id 取附件列（只取本專案、未綁訊息嗰批——呼叫端唔使再驗） ==

trait get_rows_by_ids
{
    // 取有效附件列（project_id 相符 + message_id=0 + pending/ready）
    public function get_rows_by_ids($project_id, $attachment_ids)
    {
        $attachment_rows = [];

        // 判定：冇 id → 空清單
        if (count($attachment_ids) < 1) {
            return $attachment_rows;
        }

        $placeholders = [];
        $bindings     = [];
        $bindings[]   = (int)$project_id;

        // 砌 IN 佔位（id 一律轉 int——唔拼接原字串）
        foreach ($attachment_ids as $attachment_id_item) {
            $placeholders[] = "?";
            $bindings[]     = (int)$attachment_id_item;
        }

        $sql = "SELECT attachment_id, filepath, filename, original_filename, mime_type, content, attachment_status"
            . " FROM attachments"
            . " WHERE project_id = ? AND message_id = 0 AND attachment_id IN (" . implode(",", $placeholders) . ")";

        $connection = sc_db()->connection("sqlite");
        $attachment_rows = $connection->select($sql, $bindings);

        return $attachment_rows;
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment save_extract_result：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：抽取完成後回填附件（content＝摘要文字、attachment_status＝ready／failed） ==
// == 上游：queue()->dispatch_polling_job（polling 首次派工前）；下游：attachments 表（SQLite UPDATE） ==

trait save_extract_result
{

    // 以 message_id＋filename 定位附件列（箱搬幾多次都對得上——唔靠會過期嘅路徑）
    public function save_extract_result($message_id, $filename, $content_text, $attachment_status)
    {
        $sql = "UPDATE attachments SET content = ?, attachment_status = ?, attachment_updated_at = ?"
            . " WHERE message_id = ? AND filename = ?";

        $bindings   = [];
        $bindings[] = (string)$content_text;
        $bindings[] = (string)$attachment_status;
        $bindings[] = date("Y-m-d H:i:s");
        $bindings[] = (int)$message_id;
        $bindings[] = (string)$filename;

        $connection = sc_db()->connection("sqlite");
        $connection->statement($sql, $bindings);
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment insert_message_attachments：attachment 域動作（trait——掛入 attachment_Class；檔＝動作）
// == 職責：推帶落地後，把箱內附件描述（meta.attachments）寫入 attachments 表（綁 message_id／chat_id）
// == 上游：queue move_queue_item_to_polling（檔案已搬到最終目錄之後）
// == 下游：attachments 表（INSERT）；時間由呼叫端提供

trait insert_message_attachments
{
    // 寫入附件列（回寫入列數；描述清單空＝0）
    public function insert_message_attachments($project_id, $chat_id, $message_id, $attachments_dir, $attachment_items)
    {
        // 判定：冇附件 → 唔動作
        if (count($attachment_items) < 1) {
            return 0;
        }

        $storage_dir = sc_ai_chats()->storage()->get_storage_dir();

        // 相對路徑（DB 存相對 storage——搬機／搬目錄唔失效）
        $relative_dir = substr($attachments_dir, strlen($storage_dir) + 1);

        $current_time = date("Y-m-d H:i:s");

        $connection = sc_db()->connection("sqlite");

        $inserted_count = 0;

        foreach ($attachment_items as $attachment_item) {
            $attachment_filename = (string)($attachment_item["filename"] ?? "");

            // 判定：冇檔名 → 跳過（描述壞）
            if ($attachment_filename === "") {
                continue;
            }

            $attachment_filepath = $relative_dir . "/" . $attachment_filename;

            $attachment_datapairs = [];
            $attachment_datapairs["project_id"]            = (int)$project_id;
            $attachment_datapairs["chat_id"]               = (int)$chat_id;
            $attachment_datapairs["message_id"]            = (int)$message_id;
            $attachment_datapairs["filepath"]              = $attachment_filepath;
            $attachment_datapairs["filename"]              = $attachment_filename;
            $attachment_datapairs["original_filename"]     = (string)($attachment_item["original_filename"] ?? "");
            $attachment_datapairs["mime_type"]             = (string)($attachment_item["mime_type"] ?? "");
            $attachment_datapairs["file_size"]             = (int)($attachment_item["file_size"] ?? 0);
            $attachment_datapairs["file_hash"]             = "";
            $attachment_datapairs["image_width"]           = (int)($attachment_item["image_width"] ?? 0);
            $attachment_datapairs["image_height"]          = (int)($attachment_item["image_height"] ?? 0);
            $attachment_datapairs["content"]               = "";
            $attachment_datapairs["attachment_status"]     = "pending";
            $attachment_datapairs["attachment_created_at"] = $current_time;
            $attachment_datapairs["attachment_updated_at"] = $current_time;

            list($insert_sql, $insert_bindings) = $connection->insert_sql("attachments", $attachment_datapairs);

            $connection->statement($insert_sql, $insert_bindings);

            $inserted_count = $inserted_count + 1;
        }

        return $inserted_count;
    }
}

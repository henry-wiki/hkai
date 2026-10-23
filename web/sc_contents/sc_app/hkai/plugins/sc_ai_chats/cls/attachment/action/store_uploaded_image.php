<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment store_uploaded_image：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：圖片落地（驗類型／大小 → sha256 命名 → 寫檔 → 寫 DB 列——message_id=0 待綁定） ==

trait store_uploaded_image
{
    //---------------------------------------------------------
    // 1. 驗大小（hkai_config attachments.max_file_size_mb）
    // 2. 驗類型（png／jpeg／gif／webp）
    // 3. sha256 命名（避免中文／重名／路徑問題）
    // 4. 寫檔＋寫 DB 列（attachment_status=pending）
    // 5. 交抽取（失敗唔阻上傳——content 留空、狀態 failed）
    //---------------------------------------------------------
    public function store_uploaded_image($project_row, $original_filename, $mime_type, $binary)
    {
        $project_slug = (string)$project_row["project_slug"];
        $project_id   = (int)$project_row["project_id"];

        $attachment_configs = hkai__core()->get_hkai_configs("attachments");
        $max_file_size_mb   = (int)$attachment_configs["max_file_size_mb"];
        $max_file_size_byte = $max_file_size_mb * 1024 * 1024;

        // 判定：超過大小上限 → 拒絕
        if (strlen($binary) > $max_file_size_byte) {
            return null;
        }

        $allowed_mime_map = [];
        $allowed_mime_map["image/png"]  = "png";
        $allowed_mime_map["image/jpeg"] = "jpg";
        $allowed_mime_map["image/gif"]  = "gif";
        $allowed_mime_map["image/webp"] = "webp";

        // 判定：唔係白名單圖片類型 → 拒絕
        if (!isset($allowed_mime_map[$mime_type])) {
            return null;
        }

        $file_extension = $allowed_mime_map[$mime_type];
        $file_hash      = hash("sha256", $binary);

        // 存檔名：hash 前 32 字元＋隨機後綴（避免不同訊息共用同一檔——刪一條唔影響另一條）
        $stored_filename = substr($file_hash, 0, 32) . "_" . bin2hex(random_bytes(4)) . "." . $file_extension;

        $uploads_dir = $this->get_uploads_dir($project_slug);
        sc_mkdir($uploads_dir);

        $absolute_filepath = $uploads_dir . "/" . $stored_filename;

        // 寫檔（失敗＝回 null；唔留 DB 孤兒）
        $write_result = @file_put_contents($absolute_filepath, $binary);
        // 判定：寫入失敗 → 拒絕
        if ($write_result === false) {
            return null;
        }

        // 圖片尺寸（非致命；取唔到＝0）
        $image_width  = 0;
        $image_height = 0;
        $image_size_info = @getimagesizefromstring($binary);
        // 判定：取到尺寸 → 記錄
        if ($image_size_info !== false) {
            $image_width  = (int)$image_size_info[0];
            $image_height = (int)$image_size_info[1];
        }

        // 相對路徑（DB 存相對 storage——換機／搬位置唔受影響）
        $folders = hkai__core()->get_hkai_configs("folders");
        $relative_filepath = $folders["uploads"] . "/" . $project_slug . "/" . $stored_filename;

        $current_time = date("Y-m-d H:i:s");

        $connection = sc_db()->connection("sqlite");

        $attachment_datapairs = [];
        $attachment_datapairs["project_id"]             = $project_id;
        $attachment_datapairs["chat_id"]                = 0;
        $attachment_datapairs["message_id"]             = 0;
        $attachment_datapairs["filepath"]               = $relative_filepath;
        $attachment_datapairs["filename"]               = $stored_filename;
        $attachment_datapairs["original_filename"]      = $original_filename;
        $attachment_datapairs["mime_type"]              = $mime_type;
        $attachment_datapairs["file_size"]              = strlen($binary);
        $attachment_datapairs["file_hash"]              = $file_hash;
        $attachment_datapairs["image_width"]            = $image_width;
        $attachment_datapairs["image_height"]           = $image_height;
        $attachment_datapairs["content"]                = "";
        $attachment_datapairs["attachment_status"]      = "ready";
        $attachment_datapairs["attachment_created_at"]  = $current_time;
        $attachment_datapairs["attachment_updated_at"]  = $current_time;

        list($insert_sql, $insert_bindings) = $connection->insert_sql("attachments", $attachment_datapairs);
        $connection->statement($insert_sql, $insert_bindings);

        $attachment_id = (int)$connection->lastInsertId();

        $attachment_row = [];
        $attachment_row["attachment_id"]     = $attachment_id;
        $attachment_row["filepath"]          = $relative_filepath;
        $attachment_row["filename"]          = $stored_filename;
        $attachment_row["original_filename"] = $original_filename;
        $attachment_row["mime_type"]         = $mime_type;

        return $attachment_row;
    }
}

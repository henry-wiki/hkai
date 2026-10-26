<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment store_uploaded_image：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：圖片落地（驗類型／大小 → sha256 命名 → 寫檔 → 寫 DB 列——message_id=0 待綁定） ==

trait store_uploaded_image
{
    //---------------------------------------------------------
    // 1. 驗大小（sc_config hkai.attachments.max_upload_size——單位 KB）
    // 2. 驗類型（png／jpeg／gif／webp）
    // 3. sha256 命名（避免中文／重名／路徑問題）
    // 4. 寫檔＋寫 DB 列（attachment_status=pending）
    // 5. 交抽取（失敗唔阻上傳——content 留空、狀態 failed）
    //---------------------------------------------------------
    public function store_uploaded_image($project_row, $original_filename, $mime_type, $binary)
    {
        $project_slug = (string)$project_row["project_slug"];
        $project_id   = (int)$project_row["project_id"];

        $max_upload_size_kb   = (int)sc_get_config("hkai.attachments.max_upload_size");
        $max_file_size_byte   = $max_upload_size_kb * 1024;

        // 判定：超過大小上限 → 拒絕
        if (strlen($binary) > $max_file_size_byte) {
            return null;
        }

        // 真實格式（讀 magic bytes——唔信 client MIME／副檔名）
        $detected_mime_type = $this->detect_image_mime_type($binary);

        // 判定：內容唔係圖片 → 拒絕（零寫入——唔污染附件目錄）
        if ($detected_mime_type === "") {
            return null;
        }

        // provider 只收 PNG／JPEG；其餘真圖片（GIF／WebP）→ 先轉 PNG
        $stored_mime_type = $detected_mime_type;
        $stored_binary    = $binary;

        // 判定：非 provider 可收格式 → 轉 provider 格式
        if (!in_array($detected_mime_type, ["image/png", "image/jpeg"])) {
            $converted_image = $this->convert_image_binary_to_provider_format($binary, $detected_mime_type);

            // 判定：轉碼失敗 → 拒絕（唔存半成品）
            if (count($converted_image) === 0) {
                return null;
            }

            $stored_mime_type = $converted_image["mime_type"];
            $stored_binary    = $converted_image["binary"];
        }

        // 判定：轉碼後超過大小上限 → 拒絕
        if (strlen($stored_binary) > $max_file_size_byte) {
            return null;
        }

        $stored_extension_map = [];
        $stored_extension_map["image/png"]  = "png";
        $stored_extension_map["image/jpeg"] = "jpg";

        $file_extension = $stored_extension_map[$stored_mime_type];
        $file_hash      = hash("sha256", $stored_binary);

        // 存檔名：hash 前 32 字元＋隨機後綴（避免不同訊息共用同一檔——刪一條唔影響另一條）
        $stored_filename = substr($file_hash, 0, 32) . "_" . bin2hex(random_bytes(4)) . "." . $file_extension;

        // 上傳目錄（每個專案一個——hkai.messages_attachments_dir 設定單一來源）
        $messages_attachments_dir = sc_get_config("hkai.messages_attachments_dir");
        $uploads_dir              = $messages_attachments_dir . "/" . $project_slug;
        sc_mkdir($uploads_dir);

        $absolute_filepath = $uploads_dir . "/" . $stored_filename;

        // 寫檔（失敗＝回 null；唔留 DB 孤兒）
        $write_result = @file_put_contents($absolute_filepath, $stored_binary);
        // 判定：寫入失敗 → 拒絕
        if ($write_result === false) {
            return null;
        }

        return $this->_insert_attachment_record($project_row, $absolute_filepath, $stored_filename, $original_filename, $stored_mime_type, $stored_binary);
    }

    // 寫 DB 附件列（store／draft 匯入共用——message_id=0 待綁定；回傳 row）
    private function _insert_attachment_record($project_row, $absolute_filepath, $stored_filename, $original_filename, $stored_mime_type, $stored_binary)
    {
        $project_id = (int)$project_row["project_id"];

        // 圖片尺寸（非致命；取唔到＝0）
        $image_width  = 0;
        $image_height = 0;
        $image_size_info = @getimagesizefromstring($stored_binary);

        // 判定：取到尺寸 → 記錄
        if ($image_size_info !== false) {
            $image_width  = (int)$image_size_info[0];
            $image_height = (int)$image_size_info[1];
        }

        // 相對路徑（DB 存相對 storage 目錄——換機／搬位置唔受影響；由實際絕對路徑反推）
        $storage_dir = sc_ai_chats()->storage()->get_storage_dir();
        $relative_filepath = substr($absolute_filepath, strlen($storage_dir) + 1);

        $current_time = date("Y-m-d H:i:s");

        $connection = sc_db()->connection("sqlite");

        $attachment_datapairs = [];
        $attachment_datapairs["project_id"]             = $project_id;
        $attachment_datapairs["chat_id"]                = 0;
        $attachment_datapairs["message_id"]             = 0;
        $attachment_datapairs["filepath"]               = $relative_filepath;
        $attachment_datapairs["filename"]               = $stored_filename;
        $attachment_datapairs["original_filename"]      = $original_filename;
        $attachment_datapairs["mime_type"]              = $stored_mime_type;
        $attachment_datapairs["file_size"]              = strlen($stored_binary);
        $attachment_datapairs["file_hash"]              = hash("sha256", $stored_binary);
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
        $attachment_row["mime_type"]         = $stored_mime_type;

        return $attachment_row;
    }

    // 真實圖片格式（只讀 magic bytes——微秒級；唔係圖片回空字串；public：draft 上傳共用）
    public function detect_image_mime_type($binary)
    {
        $head_bytes = substr($binary, 0, 12);

        // PNG 簽名（hex2bin——避免轉義字元被寫成真換行）
        $png_signature = hex2bin("89504e470d0a1a0a");

        // 判定：PNG 簽名
        if (str_starts_with($head_bytes, $png_signature)) {
            return "image/png";
        }

        // 判定：JPEG 簽名
        if (str_starts_with($head_bytes, "\xFF\xD8\xFF")) {
            return "image/jpeg";
        }

        // 判定：GIF87a
        if (str_starts_with($head_bytes, "GIF87a")) {
            return "image/gif";
        }

        // 判定：GIF89a
        if (str_starts_with($head_bytes, "GIF89a")) {
            return "image/gif";
        }

        $riff_magic = substr($binary, 0, 4);
        $format_magic = substr($binary, 8, 4);

        // 判定：RIFF 容器
        if ($riff_magic === "RIFF") {
            // 判定：RIFF 內係 WEBP
            if ($format_magic === "WEBP") {
                return "image/webp";
            }
        }

        return "";
    }

    // 轉 provider 格式（GIF→PNG 保留透明；WebP 等→JPEG 白底；失敗回空 array；public：draft 上傳共用）
    public function convert_image_binary_to_provider_format($binary, $detected_mime_type)
    {
        $image_resource = @imagecreatefromstring($binary);

        // 判定：GD 解唔到 → 回空
        if ($image_resource === false) {
            return [];
        }

        $image_width  = imagesx($image_resource);
        $image_height = imagesy($image_resource);

        $canvas = imagecreatetruecolor($image_width, $image_height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent_color = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $image_width, $image_height, $transparent_color);
        imagecopy($canvas, $image_resource, 0, 0, 0, 0, $image_width, $image_height);

        // 判定：GIF → PNG（保留透明；動畫取第一幀）
        if ($detected_mime_type === "image/gif") {
            ob_start();
            $write_result = imagepng($canvas);
            $output_binary = ob_get_clean();

            imagedestroy($image_resource);
            imagedestroy($canvas);

            // 判定：寫 PNG 失敗 → 回空
            if ($write_result === false) {
                return [];
            }

            return ["mime_type" => "image/png", "binary" => (string)$output_binary];
        }

        // 其餘（WebP 等）→ JPEG 白底（避免透明變黑；體積遠細過 PNG）
        $white_color = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $image_width, $image_height, $white_color);
        imagecopy($canvas, $image_resource, 0, 0, 0, 0, $image_width, $image_height);

        ob_start();
        $write_result = imagejpeg($canvas, null, 90);
        $output_binary = ob_get_clean();

        imagedestroy($image_resource);
        imagedestroy($canvas);

        // 判定：寫 JPEG 失敗 → 回空
        if ($write_result === false) {
            return [];
        }

        return ["mime_type" => "image/jpeg", "binary" => (string)$output_binary];
    }
}

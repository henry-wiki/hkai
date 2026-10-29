<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\upload;

// == sc_ai_chats attachment process_uploaded_image：最終存檔（驗大小／格式 → 轉 PNG／JPEG → 寫檔＋縮圖＋meta；回資料 array）（trait——掛入 attachment_Class） ==
// == 上游：upload_draft_attachment（上傳端點）；下游：attachment_Class 縮圖／資訊檔＋draft_Class meta／路徑 ==

trait process_uploaded_image
{

    // 最終存檔（驗大小／真假格式 → 轉 PNG／JPEG → 寫檔＋縮圖＋meta；回資料 array）
    public function process_uploaded_image($project_slug, $chat_sid, $original_filename, $binary)
    {
        $max_upload_size_kb = (int)sc_get_config("hkai.draft.max_upload_size");
        $max_file_size_byte = $max_upload_size_kb * 1024;

        // 判定：超過大小上限 → 空
        if (strlen($binary) > $max_file_size_byte) {
            return [];
        }

        $detected_mime_type = sc_ai_chats()->attachment()->detect_image_mime_type($binary);

        // 判定：唔係圖片 → 空
        if ($detected_mime_type === "") {
            return [];
        }

        $stored_mime_type = $detected_mime_type;
        $stored_binary    = $binary;

        // 判定：非 provider 格式 → 轉 PNG／JPEG
        if (!in_array($detected_mime_type, ["image/png", "image/jpeg"])) {
            $converted_image = sc_ai_chats()->attachment()->webp_to_jpg_or_gif_to_png($binary, $detected_mime_type);

            // 判定：轉碼失敗 → 空
            if (count($converted_image) < 1) {
                return [];
            }

            $stored_mime_type = (string)$converted_image["mime_type"];
            $stored_binary    = (string)$converted_image["binary"];
        }

        $extension_map = [];
        $extension_map["image/png"]  = "png";
        $extension_map["image/jpeg"] = "jpg";

        $file_extension   = $extension_map[$stored_mime_type];
        $now_milliseconds = (int)floor(microtime(true) * 1000);
        $random_suffix    = bin2hex(random_bytes(3));
        $stored_filename  = $now_milliseconds . "-" . $random_suffix . "." . $file_extension;
        $thumb_filename   = $now_milliseconds . "-" . $random_suffix . ".thumb.jpg";

        $attachments_dir = sc_ai_chats()->draft()->get_draft_item_dir($project_slug, $chat_sid) . "/attachments";
        sc_mkdir($attachments_dir);

        $absolute_filepath = $attachments_dir . "/" . $stored_filename;
        $thumb_filepath    = $attachments_dir . "/" . $thumb_filename;

        $write_result = @file_put_contents($absolute_filepath, $stored_binary);

        // 判定：寫入失敗 → 空
        if ($write_result === false) {
            return [];
        }

        $thumb_written = sc_ai_chats()->attachment()->write_thumbnail($stored_binary, $thumb_filepath);

        // 判定：縮圖失敗 → thumb 欄留空（前端用 icon）
        if ($thumb_written === false) {
            $thumb_filename = "";
        }

        $image_width  = 0;
        $image_height = 0;
        $image_size_info = @getimagesizefromstring($stored_binary);

        // 判定：取到尺寸 → 記錄
        if ($image_size_info !== false) {
            $image_width  = (int)$image_size_info[0];
            $image_height = (int)$image_size_info[1];
        }

        $stored_info = [];
        $stored_info["filename"]          = $stored_filename;
        $stored_info["original_filename"] = $original_filename;
        $stored_info["thumb"]             = $thumb_filename;
        $stored_info["mime_type"]         = $stored_mime_type;
        $stored_info["file_size"]         = strlen($stored_binary);
        $stored_info["image_width"]       = $image_width;
        $stored_info["image_height"]      = $image_height;
        $stored_info["created_at"]        = (int)floor(microtime(true) * 1000);

        // 附件資訊 json（同圖同名）＋重建 meta 清單
        sc_ai_chats()->attachment()->write_attachment_info($project_slug, $chat_sid, $stored_info);
        sc_ai_chats()->draft()->refresh_draft_meta_for_attachments($project_slug, $chat_sid);

        return $stored_info;
    }
}

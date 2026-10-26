<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment import_draft_file：草稿檔匯入正式附件（trait——掛入 attachment_Class） ==
// == 職責：draft 檔 rename 去 messages/attachments/<slug>/ ＋ 寫 DB 列（message_id=0 待綁定） ==
// == 上游：prompt_send（送出時匯入）；下游：detect／convert helper＋_insert_attachment_record ==

trait import_draft_file
{

    // 匯入一個草稿檔（回 attachment row；失敗 null）
    public function import_draft_file($project_row, $absolute_path, $original_filename)
    {
        $project_slug = (string)$project_row["project_slug"];

        // 判定：來源檔唔存在 → null
        if (!is_file($absolute_path)) {
            return null;
        }

        $binary = @file_get_contents($absolute_path);

        // 判定：讀唔到 → null
        if ($binary === false || $binary === "") {
            return null;
        }

        $detected_mime_type = $this->detect_image_mime_type($binary);

        // 判定：唔係圖片 → null
        if ($detected_mime_type === "") {
            return null;
        }

        $stored_mime_type = $detected_mime_type;
        $stored_binary    = $binary;

        // 判定：非 provider 格式 → 轉 PNG／JPEG
        if (!in_array($detected_mime_type, ["image/png", "image/jpeg"])) {
            $converted_image = $this->convert_image_binary_to_provider_format($binary, $detected_mime_type);

            // 判定：轉碼失敗 → null
            if (count($converted_image) < 1) {
                return null;
            }

            $stored_mime_type = (string)$converted_image["mime_type"];
            $stored_binary    = (string)$converted_image["binary"];
        }

        $extension_map = [];
        $extension_map["image/png"]  = "png";
        $extension_map["image/jpeg"] = "jpg";

        $file_extension  = $extension_map[$stored_mime_type];
        $file_hash       = hash("sha256", $stored_binary);
        $stored_filename = substr($file_hash, 0, 32) . "_" . bin2hex(random_bytes(4)) . "." . $file_extension;

        $messages_attachments_dir = sc_get_config("hkai.messages_attachments_dir");
        $uploads_dir              = $messages_attachments_dir . "/" . $project_slug;
        sc_mkdir($uploads_dir);

        $absolute_filepath = $uploads_dir . "/" . $stored_filename;

        // 搬檔（同一磁碟 rename——唔留草稿副本）
        $moved = @rename($absolute_path, $absolute_filepath);

        // 判定：搬唔到 → null
        if ($moved === false) {
            return null;
        }

        return $this->_insert_attachment_record($project_row, $absolute_filepath, $stored_filename, $original_filename, $stored_mime_type, $stored_binary);
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment store_uploaded_document：文件附件落地（抽出文字 → 存 {hash}.txt → 入 DB）（trait——掛入 attachment_Class） ==
// == 上游：prompt request upload_attachment（文件分支） ==
// == 下游：attachments 表（INSERT——_insert_attachment_record）；原檔不落地 ==

trait store_uploaded_document
{
    // 文件落地（存 .txt；失敗回 null）
    public function store_uploaded_document($project_row, $original_filename, $text_value)
    {
        $project_slug = (string)$project_row["project_slug"];

        $max_upload_size_kb = (int)sc_get_config("hkai.attachments.max_upload_size");
        $max_file_size_byte = $max_upload_size_kb * 1024;

        $stored_binary = (string)$text_value;

        // 判定：超過大小上限 → 拒收
        if (strlen($stored_binary) > $max_file_size_byte) {
            return null;
        }

        $stored_mime_type = "text/plain";
        $file_hash        = hash("sha256", $stored_binary);

        // 存檔名：hash 前 32 字元＋隨機後綴（一律 .txt——永不可執行）
        $stored_filename = substr($file_hash, 0, 32) . "_" . bin2hex(random_bytes(4)) . ".txt";

        $messages_attachments_dir = sc_get_config("hkai.messages_attachments_dir");
        $uploads_dir              = $messages_attachments_dir . "/" . $project_slug;

        sc_mkdir($uploads_dir);

        $absolute_filepath = $uploads_dir . "/" . $stored_filename;

        $write_result = @file_put_contents($absolute_filepath, $stored_binary);

        // 判定：寫入失敗 → 拒收（唔留 DB 孤兒）
        if ($write_result === false) {
            return null;
        }

        return $this->_insert_attachment_record($project_row, $absolute_filepath, $stored_filename, $original_filename, $stored_mime_type, $stored_binary);
    }
}

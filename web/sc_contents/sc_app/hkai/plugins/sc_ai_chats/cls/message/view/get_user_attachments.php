<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message get_user_attachments：message 域視圖（trait——掛入 view_Class；檔＝視圖） ==
// == 職責：user prompt 上方附件列（多張——沿用 composer 附件 chip 樣式） ==
// == 上游：get_chat_message_slot（user_message slot 內、user_prompt 之前）；下游：poll()->get_existing_message_dir＋箱內 meta.json ==

trait get_user_attachments
{
    // 使用者訊息附件列 HTML（冇附件 → 空字串）
    public function get_user_attachments($row)
    {
        $message_id  = (int)($row["message_id"] ?? 0);
        $message_dir = "";

        // 判定：message_id 唔合法 → 空
        if ($message_id > 0) {
            $message_dir = sc_ai_chats()->poll()->get_existing_message_dir($message_id);
        }

        // 判定：箱唔存在 → 空
        if ($message_dir === "") {
            return "";
        }

        $meta_path = $message_dir . "/meta.json";

        // 判定：冇 meta.json → 空
        if (is_file($meta_path) === false) {
            return "";
        }

        $meta_data = json_decode((string)@file_get_contents($meta_path), true);

        // 判定：解析失敗 → 空
        if (is_array($meta_data) === false) {
            return "";
        }

        $attachments = (array)($meta_data["attachments"] ?? []);

        // 判定：冇附件 → 空
        if (count($attachments) < 1) {
            return "";
        }

        $attachment_items = [];

        // 逐張附件：砌一個 chip（縮圖＋原檔名＋大小）
        foreach ($attachments as $attachment_item) {
            $attachment_filename      = (string)($attachment_item["filename"] ?? "");
            $attachment_original_name = (string)($attachment_item["original_filename"] ?? $attachment_filename);
            $attachment_mime_type     = (string)($attachment_item["mime_type"] ?? "");
            $attachment_file_size     = (int)($attachment_item["file_size"] ?? 0);

            // 判定：冇檔名 → 跳過
            if ($attachment_filename === "") {
                continue;
            }

            $attachment_size_text = $this->_format_attachment_size($attachment_file_size);
            $attachment_thumb_url = "/request/hkai/serve_attachment/?message_id=" . $message_id . "&filename=" . $attachment_filename . "&variant=thumb";
            $attachment_full_url  = "/request/hkai/serve_attachment/?message_id=" . $message_id . "&filename=" . $attachment_filename . "&variant=full";
            $attachment_name_html = sc_html_encode($attachment_original_name);
            $attachment_thumb_html = "<i class='fa-solid fa-paperclip'></i>";

            // 判定：圖片 → 出縮圖；其他 → 萬字夾
            if (strpos($attachment_mime_type, "image/") === 0) {
                $attachment_thumb_html = "<img class='sc_composer__attachment_thumb' src='{$attachment_thumb_url}' alt='{$attachment_name_html}'>";
            }

            $chip = [];
            $chip[] = "<a class='sc_composer__attachment' data-state='done' href='{$attachment_full_url}' target='_blank' rel='noopener'>";
                $chip[] = $attachment_thumb_html;
                $chip[] = "<span class='sc_composer__attachment_name'>{$attachment_name_html}</span>";
                $chip[] = "<span class='sc_composer__attachment_size'>{$attachment_size_text}</span>";
            $chip[] = "</a>";

            $attachment_items[] = implode("", $chip);
        }

        // 判定：全部附件都唔合格式 → 空
        if (count($attachment_items) < 1) {
            return "";
        }

        $attachments_html = [];

        $attachments_html[] = "<div class='user_attachments' data-slot='user_attachments'>";
            $attachments_html[] = implode("", $attachment_items);
        $attachments_html[] = "</div>";

        return implode("", $attachments_html);
    }

    // 檔案大小顯示（B／KB／MB——一個小數）
    private function _format_attachment_size($size_bytes)
    {
        // 判定：小於 1 KB → B
        if ($size_bytes < 1024) {
            return $size_bytes . " B";
        }

        // 判定：小於 1 MB → KB
        if ($size_bytes < 1024 * 1024) {
            return number_format($size_bytes / 1024, 1) . " KB";
        }

        return number_format($size_bytes / (1024 * 1024), 1) . " MB";
    }
}

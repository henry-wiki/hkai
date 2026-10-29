<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment delete_draft_attachment：刪草稿附件三個檔（trait——掛入 attachment_Class） ==
// == 職責：以檔名主體（filekey）glob 出所有變體（主檔／縮圖／資訊 json）逐個刪；只刪檔，唔改 meta ==
// == 上游：request_delete_draft_attachment（端點）；下游：draft_Class get_draft_item_dir ==

trait delete_draft_attachment
{

    // 刪一張草稿附件連所有同名變體（回主檔本身有冇存在）
    public function delete_draft_attachment($project_slug, $chat_sid, $filename)
    {
        $attachments_dir = sc_ai_chats()->draft()->get_draft_item_dir($project_slug, $chat_sid) . "/attachments";
        $file_key        = pathinfo($filename, PATHINFO_FILENAME);
        $file_path       = $attachments_dir . "/" . $filename;

        // 判定：主檔存在（回應旗標）→ true
        if (is_file($file_path)) {
            $deleted = true;
        } else {
            $deleted = false;
        }

        $variant_paths = glob($attachments_dir . "/" . $file_key . ".*");

        // 判定：glob 失敗 → 當空清單
        if ($variant_paths === false) {
            $variant_paths = [];
        }

        // 逐個同名變體刪（唔理副檔名：.png／.jpg／.thumb.jpg／.png.json）
        foreach ($variant_paths as $variant_path) {
            // 判定：係普通檔 → 刪
            if (is_file($variant_path)) {
                @unlink($variant_path);
            }
        }

        return $deleted;
    }
}

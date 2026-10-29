<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment backfill_extract_results：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：抽取完成後，由箱內 attachments 讀回每張圖嘅 meta.json，回填 DB（content＝summary_text、attachment_status） ==
// == 上游：queue()->dispatch_polling_item（polling 派工前一次）；下游：save_extract_result（attachments 表） ==

trait backfill_extract_results
{
    // 逐張回填（回填張數；冇 meta 嘅圖＝未抽完，跳過）
    public function backfill_extract_results($message_id, $box_dir)
    {
        $attachments_dir = $box_dir . "/attachments";
        $meta_file       = $box_dir . "/meta.json";

        // 判定：meta 記咗永久附件庫 → 改讀該目錄（出帶後附件已搬出箱）
        if (is_file($meta_file) === true) {
            $meta_data = json_decode((string)@file_get_contents($meta_file), true);
            $meta_attachments_dir = (string)($meta_data["attachments_dir"] ?? "");

            // 判定：目錄存在 → 使用
            if ($meta_attachments_dir !== "" && is_dir($meta_attachments_dir) === true) {
                $attachments_dir = $meta_attachments_dir;
            }
        }

        $file_paths      = glob($attachments_dir . "/*");

        // 判定：冇附件目錄／冇檔 → 0
        if ($file_paths === false) {
            return 0;
        }

        $backfilled_count = 0;

        // 逐個實檔：只處理圖片
        foreach ($file_paths as $file_path_item) {
            $file_name   = basename($file_path_item);
            $file_suffix = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // 判定：縮圖／非圖片 → 跳過
            if (strpos($file_name, ".thumb.") !== false) {
                continue;
            }

            // 判定：副檔名唔係圖片 → 跳過
            if (!in_array($file_suffix, ["png", "jpg", "jpeg", "webp", "gif", "bmp"], true)) {
                continue;
            }

            $meta_data = $this->_read_extract_meta($attachments_dir, $file_name);

            // 判定：未有抽取結果 → 跳過（未抽完）
            if ($meta_data === null) {
                continue;
            }

            $summary_text      = (string)($meta_data["summary_text"] ?? "");
            $file_state        = (string)($meta_data["state"] ?? "done");
            $attachment_status = "ready";

            // 判定：該張抽取失敗 → 標 failed
            if ($file_state === "failed") {
                $attachment_status = "failed";
            }

            sc_ai_chats()->attachment()->save_extract_result($message_id, $file_name, $summary_text, $attachment_status);

            $backfilled_count++;
        }

        return $backfilled_count;
    }

    // 讀單張圖嘅抽取結果（新命名 <stem>.meta.json；舊命名 <檔名>.json 後備）
    private function _read_extract_meta($attachments_dir, $file_name)
    {
        $file_stem = pathinfo($file_name, PATHINFO_FILENAME);

        $meta_candidates   = [];
        $meta_candidates[] = $attachments_dir . "/" . $file_stem . ".meta.json";
        $meta_candidates[] = $attachments_dir . "/" . $file_name . ".json";

        // 逐個候選：有就用
        foreach ($meta_candidates as $meta_path_item) {
            // 判定：檔案唔存在 → 下一個
            if (!is_file($meta_path_item)) {
                continue;
            }

            $meta_data = json_decode(@file_get_contents($meta_path_item), true);

            // 判定：可解析 → 回傳
            if (is_array($meta_data)) {
                return $meta_data;
            }
        }

        return null;
    }
}

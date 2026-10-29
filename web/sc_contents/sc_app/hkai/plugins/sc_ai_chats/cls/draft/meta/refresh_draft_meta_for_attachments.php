<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\meta;

// == sc_ai_chats draft refresh_draft_meta_for_attachments：由附件 json 重建 meta 清單（trait——掛入 draft_Class） ==
// == 職責：掃 attachments/*.json（檔名排序）→ 讀每份資訊 → 覆寫 meta.attachments ==
// == 上游：上傳完成／刪檔／送出匯入／fetch；下游：draft_Class get_draft_item_dir／set_draft_meta ==

trait refresh_draft_meta_for_attachments
{

    // 重建 meta 附件清單（回附件數；清單以磁碟 json 為唯一真相）
    public function refresh_draft_meta_for_attachments($project_slug, $chat_sid)
    {
        $attachments_dir = $this->get_draft_item_dir($project_slug, $chat_sid) . "/attachments";
        $attachment_list = [];

        // 判定：附件目錄未存在 → 清單歸空
        if (!is_dir($attachments_dir)) {
            $this->set_draft_meta($project_slug, $chat_sid, ["attachments" => $attachment_list]);

            return 0;
        }

        $dir_entries = scandir($attachments_dir);

        // 判定：掃唔到目錄 → 清單歸空
        if ($dir_entries === false) {
            $this->set_draft_meta($project_slug, $chat_sid, ["attachments" => $attachment_list]);

            return 0;
        }

        // 逐個 json 讀資訊（scandir 已按檔名排序——＝上傳先後）
        foreach ($dir_entries as $dir_entry) {
            // 判定：唔係 .json → 跳過（圖檔／縮圖）
            if (substr($dir_entry, -5) !== ".json") {
                continue;
            }

            $info_json = @file_get_contents($attachments_dir . "/" . $dir_entry);
            $info_data = json_decode((string)$info_json, true);

            // 判定：解唔到／唔係陣列 → 跳過（壞檔唔入清單）
            if (!is_array($info_data)) {
                continue;
            }

            $attachment_list[] = $info_data;
        }

        $this->set_draft_meta($project_slug, $chat_sid, ["attachments" => $attachment_list]);

        return count($attachment_list);
    }
}

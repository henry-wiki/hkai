<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\upload;

// == sc_ai_chats attachment write_attachment_info：寫附件資訊 json（trait——掛入 attachment_Class） ==
// == 職責：attachments/<圖檔名>.json——檔名、原始檔名、縮圖、尺寸等（meta 清單由此重建） ==
// == 上游：process_uploaded_image；下游：無（純寫檔） ==

trait write_attachment_info
{

    // 寫附件資訊 json（同圖同名＋.json；成功＝true）
    public function write_attachment_info($project_slug, $chat_sid, $attachment_info)
    {
        $attachments_dir = sc_ai_chats()->draft()->get_draft_item_dir($project_slug, $chat_sid) . "/attachments";
        $info_filepath   = $attachments_dir . "/" . $attachment_info["filename"] . ".json";

        $write_result = sc_write_json($info_filepath, $attachment_info);

        return ($write_result !== false);
    }
}

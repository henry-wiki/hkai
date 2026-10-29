<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_poll_item：poll file 子域動作（trait——掛入 file_Class；檔＝動作） ==
// == 職責：由 message_id 取箱內 meta（完整 poll item——身份＋attachments＋status） ==
// == 上游：queue()->dispatch_polling_job（逐箱派工）；下游：poll get_existing_message_dir（路徑唯一來源） ==

trait get_poll_item
{
    // 取箱內 meta（非 array／箱唔存在 → null）
    public function get_poll_item($message_id)
    {
        $box_dir = sc_ai_chats()->poll()->get_existing_message_dir($message_id);

        // 判定：搵唔到箱 → null
        if ($box_dir === "") {
            return null;
        }

        $meta_data = json_decode(@file_get_contents($box_dir . "/meta.json"), true);

        // 判定：meta 讀唔到 → null
        if (!is_array($meta_data)) {
            return null;
        }

        return $meta_data;
    }
}

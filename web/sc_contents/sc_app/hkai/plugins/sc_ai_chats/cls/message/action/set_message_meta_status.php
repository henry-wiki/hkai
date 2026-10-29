<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\action;

// == sc_ai_chats message set_message_meta_status：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 職責：更新箱內 meta.json 嘅 status（只改 status 一欄；目錄／其餘欄位零改動） ==
// == 上游：queue enqueue（queue）／move to polling（pending／polling）／promote（polling）／poll 狀態落地；下游：sc_write_json ==

trait set_message_meta_status
{
    // 寫 meta.status（回 bool；檔缺失／壞 → false）
    public function set_message_meta_status($message_dir, $status)
    {
        $meta_file = $message_dir . "/meta.json";

        // 判定：meta 唔存在 → 唔動作
        if (!is_file($meta_file)) {
            return false;
        }

        $meta_data = json_decode(file_get_contents($meta_file), true);

        // 判定：解析唔到 → 唔動作
        if (!is_array($meta_data)) {
            return false;
        }

        // 判定：狀態已經一樣 → 唔重寫（輪詢每拍都會叫）
        if ((string)($meta_data["status"] ?? "") === (string)$status) {
            return true;
        }

        $meta_data["status"] = $status;

        return sc_write_json($meta_file, $meta_data);
    }
}

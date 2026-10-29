<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\meta;

// == sc_ai_chats draft get_draft_meta：由 project_slug＋chat_sid 讀 meta（冇／壞 → 空）（trait——掛入 draft_Class） ==
// == 上游：draft_fetch／上傳／送出／附件重建 ==

trait get_draft_meta
{

    // 取草稿 meta（project_slug＋chat_sid → 陣列；冇／壞 → 空）
    public function get_draft_meta($project_slug, $chat_sid)
    {
        $draft_dir = $this->get_draft_item_dir($project_slug, $chat_sid);
        $meta_file = $draft_dir . "/meta.json";

        // 判定：meta 檔唔存在 → 空
        if (!is_file($meta_file)) {
            return [];
        }

        $meta_data = json_decode((string)@file_get_contents($meta_file), true);

        // 判定：解唔到／唔係物件 → 空
        if (!is_array($meta_data)) {
            return [];
        }

        return $meta_data;
    }
}

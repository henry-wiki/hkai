<?php

namespace sc\app\hkai\plugins\sc_ai_chats\draft\action;

// == sc_ai_chats draft finalize_draft：草稿定稿（trait——掛入 draft_Class；cls/draft/action/） ==
// == 職責：上帶前最後一次寫入草稿（chat_id＋status=pending）——之後輸送帶只搬箱，零改寫 ==
// == 上游：prompt_send 工序 6；下游：draft get_draft_item_dir／sc_write_json ==

trait finalize_draft
{
    // 定稿（回 bool；寫唔到＝false）
    public function finalize_draft($project_slug, $chat_sid, $chat_row)
    {
        $chat_id   = (int)$chat_row["chat_id"];
        $draft_dir = sc_ai_chats()->draft()->get_draft_item_dir($project_slug, $chat_sid);
        $meta_file = $draft_dir . "/meta.json";

        // 判定：草稿 meta 唔存在 → false（無箱可定稿）
        if (!is_file($meta_file)) {
            return false;
        }

        $draft_meta = json_decode(file_get_contents($meta_file), true);

        // 判定：解析唔到 → false（唔可以喺壞箱上面蓋章）
        if (!is_array($draft_meta)) {
            return false;
        }

        // 定稿內容：①隊伍身份 chat_id ②狀態（提交即 pending——其餘一個字都唔改）
        $draft_meta["chat_id"] = (int)$chat_id;
        $draft_meta["status"]  = "pending";

        $written = sc_write_json($meta_file, $draft_meta);

        // 判定：寫唔到 → false（上帶前必需要成功）
        if ($written === false) {
            return false;
        } else {
            return true;
        }
    }
}

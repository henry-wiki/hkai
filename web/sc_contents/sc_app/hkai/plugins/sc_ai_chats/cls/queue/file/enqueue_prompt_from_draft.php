<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue enqueue_prompt_from_draft：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：上帶——建排隊路徑，然後將整份草稿目錄 rename 入去（內容零加工、零改寫） ==
// == 上游：prompt_send 工序 6；下游：sc_config hkai.messages_queue_dir／draft get_draft_item_dir／sc_mkdir ==

trait enqueue_prompt_from_draft
{
    // 上帶（回 true；搬唔到＝例外）
    public function enqueue_prompt_from_draft($project_slug, $chat_sid)
    {
        $draft_dir       = sc_ai_chats()->draft()->get_draft_item_dir($project_slug, $chat_sid);
        $draft_meta_file = $draft_dir . "/meta.json";

        // 判定：草稿 meta 唔存在 → 例外（未定稿，唔應該上帶）
        if (!is_file($draft_meta_file)) {
            throw new \RuntimeException("queue draft meta missing: " . $draft_meta_file);
        }

        $draft_meta = json_decode(file_get_contents($draft_meta_file), true);

        // 判定：解析唔到 → 例外
        if (!is_array($draft_meta)) {
            throw new \RuntimeException("queue draft meta invalid: " . $draft_meta_file);
        }

        // 判定：草稿冇文字又冇附件 → 例外（空項目唔准上帶）
        $draft_prompt_text = trim((string)($draft_meta["prompt_text"] ?? ""));
        $draft_attachments = $draft_meta["attachments"] ?? [];

        if ($draft_prompt_text === "" && count($draft_attachments) < 1) {
            throw new \RuntimeException("queue draft empty: " . $draft_meta_file);
        }

        // 身份由箱內 meta 取（唔用外部傳入）
        $prompt_id = (string)($draft_meta["prompt_id"] ?? "");

        // 判定：冇 prompt_id → 例外（避免砌出 queue/<slug>/<sid>/ 空殼）
        if ($prompt_id === "") {
            throw new \RuntimeException("queue draft missing prompt_id: " . $draft_meta_file);
        }

        // 判定：冇 project_id → 例外（建訊息列／專案路徑都要用）
        if ((int)($draft_meta["project_id"] ?? 0) < 1) {
            throw new \RuntimeException("queue draft missing project_id: " . $draft_meta_file);
        }

        $messages_queue_dir = sc_get_config("hkai.messages_queue_dir");

        $queue_chat_dir = $messages_queue_dir . "/" . $project_slug . "/" . $chat_sid;

        sc_mkdir($queue_chat_dir);

        $queue_item_dir = $queue_chat_dir . "/" . $prompt_id;

        // 上帶：整個草稿目錄 rename（打包已完成——箱內一個字都唔改）
        $moved = @rename($draft_dir, $queue_item_dir);

        // 判定：搬唔到 → 例外（上帶失敗唔可以半途）
        if ($moved === false) {
            throw new \RuntimeException("queue move failed: " . $draft_dir);
        } else {
            // 入隊：箱內 status = queue（等推帶）
            sc_ai_chats()->message()->set_message_meta_status($queue_item_dir, "queue");

            return true;
        }
    }
}

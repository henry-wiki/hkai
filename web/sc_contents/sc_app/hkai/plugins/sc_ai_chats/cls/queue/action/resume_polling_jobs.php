<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue resume_polling_jobs：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：重入通知——掃該對話 polling 箱，status=polling 且無 worker 心跳 → 逐個 dispatch_polling_item（補回未開工嘅回覆） ==
// == 上游：request/prompt/prompt_resume（頁面重入時一次）；下游：poll get_poll_chat_dir／process find_worker_pids／本域 dispatch_polling_item ==

trait resume_polling_jobs
{
    // 掃描並補派工（回實際派工數；status 唔係 polling／worker 仍活＝跳過）
    public function resume_polling_jobs($project_slug, $chat_sid)
    {
        $resumed_count = 0;

        $poll_chat_dir = sc_ai_chats()->poll()->get_poll_chat_dir($project_slug, $chat_sid);
        $message_dirs  = glob($poll_chat_dir . "/*", GLOB_ONLYDIR);

        // 判定：冇箱 → 0
        if ($message_dirs === false) {
            return 0;
        }

        // 逐個箱：只補派「已入 polling、未有人開工」嘅
        foreach ($message_dirs as $message_dir_item) {
            $meta_file = $message_dir_item . "/meta.json";

            // 判定：冇 meta → 跳過
            if (!is_file($meta_file)) {
                continue;
            }

            $poll_item = json_decode(file_get_contents($meta_file), true);

            // 判定：meta 壞 → 跳過
            if (!is_array($poll_item)) {
                continue;
            }

            $message_status = (string)($poll_item["status"] ?? "");

            // 判定：終態 → 跳過（唔應喺 polling）
            if (in_array($message_status, ["completed", "failed", "cancelled"], true)) {
                continue;
            }

            $message_id      = (int)($poll_item["message_id"] ?? 0);
            $extract_job_dir = sc_get_config("hkai.image_extract_dir") . "/" . $message_id;

            // 判定：pending 而抽取 job 已存在 → 跳過（進度交 poll 嘅 promote 接手）
            if ($message_status === "pending" && is_dir($extract_job_dir)) {
                continue;
            }

            $chat_id = (int)($poll_item["chat_id"] ?? 0);

            // 判定：身份唔齊 → 跳過
            if ($chat_id < 1 || $message_id < 1) {
                continue;
            }

            $worker_pids = sc_ai_chats()->process()->find_worker_pids($chat_id, $message_id);

            // 判定：worker 仍活 → 跳過（唔重複叫）
            if (count($worker_pids) > 0) {
                continue;
            }

            $is_dispatched = sc_ai_chats()->queue()->dispatch_polling_item($poll_item);

            // 判定：今次真係送出通知 → 計數
            if ($is_dispatched === true) {
                $resumed_count++;
            }
        }

        return $resumed_count;
    }
}

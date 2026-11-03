<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue resume_polling_jobs：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：重入通知——掃該對話 polling 箱，status=polling 且無 worker 心跳 → 逐個 dispatch_polling_item（補回未開工嘅回覆） ==
// == 上游：request/prompt/prompt_resume（頁面重入時一次）；下游：poll revive／reset_partial_response／is_msg_worker_alive、本域 dispatch_polling_item ==

trait resume_polling_jobs
{
    // 掃描並補派工（回實際派工數；status 唔係 polling／worker 仍活＝跳過）
    public function resume_polling_jobs($project_slug, $chat_sid)
    {
        $resumed_count = 0;

        // 先復活「worker 中途死亡」而被打成 failed 嘅訊息（搬返 polling＋清洗＋重派）
        sc_ai_chats()->poll()->revive_dead_worker_messages($project_slug, $chat_sid);

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

            // 主人健在？（claim 嘅 worker_pid 現查＋心跳認名——台帳係歷史記錄，唔可以當死活）
            $worker_alive = $this->resume_has_live_worker($message_dir_item);

            // 判定：worker 仍活 → 跳過（唔重複叫）
            if ($worker_alive === true) {
                continue;
            }

            // 判定：半截回覆（worker 死前已開工）→ 先清洗，避免新舊內容混埋
            if (in_array($message_status, ["polling", "queued", "connecting", "summarizing", "replying"], true)) {
                sc_ai_chats()->poll()->reset_partial_response($chat_id, $message_id);
            }

            $is_dispatched = sc_ai_chats()->queue()->dispatch_polling_item($poll_item);

            // 判定：今次真係送出通知 → 計數
            if ($is_dispatched === true) {
                $resumed_count++;
            }
        }

        return $resumed_count;
    }

    // 箱內 claim 嘅 worker 仲生唔生（無 claim／壞檔＝死）
    private function resume_has_live_worker($message_dir)
    {
        $claim_file = $message_dir . "/msg_claim.json";

        // 判定：冇 claim → 當死
        if (is_file($claim_file) === false) {
            return false;
        }

        $claim_data = json_decode(file_get_contents($claim_file), true);

        // 判定：claim 壞 → 當死
        if (is_array($claim_data) === false) {
            return false;
        }

        $worker_pid = (int)($claim_data["worker_pid"] ?? 0);

        // 判定：pid 無效 → 當死
        if ($worker_pid < 1) {
            return false;
        }

        return sc_ai_chats()->poll()->is_msg_worker_alive($worker_pid);
    }
}

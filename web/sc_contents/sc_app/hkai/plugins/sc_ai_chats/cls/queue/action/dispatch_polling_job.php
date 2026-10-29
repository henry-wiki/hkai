<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\action;

// == sc_ai_chats queue dispatch_polling_job：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：掃全部 polling 箱，逐箱交 dispatch_polling_item（pending → 抽取器；polling → agent） ==
// == 標記：notify.image_extractor／notify.agent（檔案存在＝已通知；只有 message_poll 依賴標記做重入檢查） ==
// == 上游：prompt_send 工序 8（新箱）／handle_message_poll（每拍）；下游：poll get_all_poll_jobs／get_poll_item ==

trait dispatch_polling_job
{
    // 掃描＋派工（無參數；回今次送出通知數）
    public function dispatch_polling_job()
    {
        $poll_jobs = sc_ai_chats()->poll()->file()->get_all_poll_jobs();

        $notified_count = 0;

        foreach ($poll_jobs as $poll_job_item) {
            $message_id = (int)$poll_job_item["message_id"];

            $poll_item = sc_ai_chats()->poll()->file()->get_poll_item($message_id);

            // 判定：箱讀唔到（已歸檔／meta 壞）→ 下一個
            if ($poll_item === null) {
                continue;
            }

            $is_notified = sc_ai_chats()->queue()->dispatch_polling_item($poll_item);

            // 判定：今次真係送出通知 → 計數
            if ($is_notified === true) {
                $notified_count++;
            }
        }

        return $notified_count;
    }
}

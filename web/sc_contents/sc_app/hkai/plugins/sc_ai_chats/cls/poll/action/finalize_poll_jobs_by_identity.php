<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll finalize_poll_jobs_by_identity：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 職責：按 project_slug＋chat_sid 對賬殘留終態（終態 → 逐條收尾；未完成唔郁） ==
// == 上游：is_queue_idle；下游：file get_poll_jobs_by_identity／message finalize_poll_job ==

trait finalize_poll_jobs_by_identity
{
    // 對賬（本對話全部終態任務 → 收尾歸檔）
    public function finalize_poll_jobs_by_identity($project_slug, $chat_sid)
    {
        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs_by_identity($project_slug, $chat_sid);

        // 逐條：終態才收尾
        foreach ($polling_jobs as $polling_job_item) {
            $message_id   = (int)$polling_job_item["message_id"];
            $message_status = (string)$polling_job_item["status"];
            $job_chat_id  = (int)$polling_job_item["chat_id"];

            // 判定：唔係終態 → 跳過
            if (sc_ai_chats()->fsm()->is_terminal_status($message_status) === false) {
                continue;
            }

            sc_ai_chats()->archive()->finalize_poll_job($job_chat_id, $message_id, $message_status);
        }
    }
}

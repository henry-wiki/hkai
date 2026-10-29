<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\guard;

// == sc_ai_chats queue is_queue_idle：queue 域守衛（trait——掛入 queue_Class；cls/queue/guard/） ==
// == 職責：推帶前驗證——按 project_slug＋chat_sid 對賬殘留終態，再查有冇任務處理中 ==
// == 上游：dequeue_prompt_message_to_poll 步驟 1；下游：poll finalize_poll_jobs_by_identity／file get_poll_jobs_by_identity ==

trait is_queue_idle
{
    // 回 bool：true＝冇任務（可推帶）；false＝有任務喺處理中
    public function is_queue_idle($project_slug, $chat_sid)
    {
        // 殘留終態對賬（唔清走會令「忙」嘅判定永真）
        sc_ai_chats()->poll()->finalize_poll_jobs_by_identity($project_slug, $chat_sid);

        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs_by_identity($project_slug, $chat_sid);

        // 判定：有任務 → 唔空閒
        if (count($polling_jobs) > 0) {
            return false;
        } else {
            return true;
        }
    }
}

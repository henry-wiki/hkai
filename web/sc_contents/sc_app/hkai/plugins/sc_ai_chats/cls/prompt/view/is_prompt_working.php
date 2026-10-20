<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt\view;

// == sc_ai_chats prompt is_prompt_working：prompt 域狀態判準（trait——掛入 prompt_Class；檔＝動作） ==
// == 職責：is_prompt_working（主按鈕判準——未完成訊息／等待列有排隊；同前端 data-working 同一語意） ==
// == 2026-09-25 自 prompt view_Class 分入；2026-09-27 init.php 撤、本 trait 獨檔 ==

trait is_prompt_working
{

    public function is_prompt_working($chat_id)
    {
        // 來源①：未完成訊息（poll job 非終態——檔案＝SSOT）
        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs($chat_id);

        $has_unfinished_message = false;
        foreach ($polling_jobs as $_polling_job_item) {
            $_status = $_polling_job_item["status"];

            // 判定：非終態 → 標記
            if (!sc_ai_chats()->fsm()->is_terminal_status($_status)) {
                $has_unfinished_message = true;
            }
        }

        // 判定：有未完成訊息 → 工作中
        if ($has_unfinished_message === true) {
            return true;
        }

        // 來源②：等待列有排隊中
        $queue_lines = sc_ai_chats()->queue()->get_queue_lines($chat_id);

        // 判定：有排隊 → 工作中
        if (count($queue_lines) > 0) {
            return true;
        }

        return false;
    }
}

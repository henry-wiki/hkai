<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll finalize_poll_jobs：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-09-27 自 poll/action.php 分獨立檔（one file one function） ==

trait finalize_poll_jobs
{

    //---------------------------------------------------------
    // 1. 掃本對話全部訊息目錄
    // 2. 未完成嘅跳過
    // 3. 終態嘅逐個交 finalize_poll_job
    //---------------------------------------------------------
    public function finalize_poll_jobs($chat_id)
    {
        // 掃本對話活躍區（messages/polling/{project_slug}/{message_id}——有 conf 嘅 dir）
        $polling_jobs = sc_ai_chats()->poll()->file()->get_poll_jobs($chat_id);

        foreach ($polling_jobs as $_polling_job_item) {
            $_message_id = (int)$_polling_job_item["message_id"];
            $_status     = $_polling_job_item["status"];

            // 判定：唔係終態（未完成／進行中）→ 唔郁，交返正常流程
            if (!sc_ai_chats()->fsm()->is_terminal_status($_status)) {
                continue;
            }

            // 終態 → 單條收尾（archive：回填→歷史→status→歸檔——次序喺嗰邊寫死）
            sc_ai_chats()->message()->finalize_poll_job($chat_id, $_message_id, $_status);
        }
    }

}

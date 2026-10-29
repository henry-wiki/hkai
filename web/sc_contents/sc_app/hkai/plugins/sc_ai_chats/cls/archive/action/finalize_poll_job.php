<?php

namespace sc\app\hkai\plugins\sc_ai_chats\archive\action;

// == sc_ai_chats archive finalize_poll_job：archive 域動作（trait——掛入 archive_Class；檔＝動作） ==
// == 2026-10-27 自 message/archive 搬入 archive/（終態收尾＋歸檔搬箱） ==

trait finalize_poll_job
{

    //---------------------------------------------------------
    // 1. 回填時間×2（移檔前）
    // 2. 段狀態終態收尾（upsert 讀檔補正＋仍 poll 段行按 msg_status 收 completed／aborted——移檔前）
    // 3. 寫歷史檔
    // 4. 標 status 入 DB
    // 5. 歸檔（搬去 archive/）——次序喺呢度
    //---------------------------------------------------------
    public function finalize_poll_job($chat_id, $message_id, $status)
    {
        // ① 終態回填（必須喺歸檔之前——時間來源為 msg_log 與各 response 檔）
        sc_ai_chats()->archive()->backfill_message_timing($chat_id, $message_id);
        sc_ai_chats()->archive()->backfill_response_timing($chat_id, $message_id);

        // ② 段狀態終態收尾（全掃 poll 檔一次過 upsert 入 DB——最後一次，補齊所有段）
        $chat_row     = sc_ai_chats()->chat()->get_chat_row($chat_id);
        $_polling_job_item = ["message_id" => $message_id, "status" => $status];
        sc_ai_chats()->message()->repo_update()->upsert_db_message_response($chat_row, $_polling_job_item);

        // 段行收邊保險（upsert 讀唔到嘅殘留 poll 段行——檔案缺失等；按終態一律收）
        sc_ai_chats()->message()->repo_update()->close_db_message_response_rows($message_id, $status);

        // ③ user 列標真實 status（content 不動——保持既有內容；四桶計數唯一入口）
        sc_ai_chats()->message()->repo_update()->update_db_message_status($message_id, $status);

        // ③-2 箱內 status＝終態（completed／failed／cancelled）——必須喺歸檔搬走之前寫
        sc_ai_chats()->message()->set_message_meta_status(sc_ai_chats()->poll()->get_poll_dir($chat_id, $message_id), $status);

        // ⑤ 歸檔（整個目錄 polling/ → archive/——最後一步：之後冇得再讀檔）
        sc_ai_chats()->archive()->archive_msg_files($chat_id, $message_id);


        // 專案統計（最後一步——唔影響收尾流程）
        sc_ai_projects()->project()->refresh_project_stats((int)$chat_row["project_id"]);
    }

}

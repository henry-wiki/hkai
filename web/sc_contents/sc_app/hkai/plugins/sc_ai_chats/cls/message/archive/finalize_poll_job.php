<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\archive;

// == sc_ai_chats message finalize_poll_job：message 域動作（trait——掛入 message_Class；檔＝動作） ==
// == 2026-09-27 自 message/archive.php 分獨立檔（one file one function） ==

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
        sc_ai_chats()->message()->backfill_message_timing($chat_id, $message_id);
        sc_ai_chats()->message()->backfill_response_timing($chat_id, $message_id);

        // ② 段狀態終態收尾（卡存在＝串流新制：一次過 flush 入 DB；冇卡＝舊制：檔案 upsert）
        $message_card = sc_ai_chats()->message()->card()->read_message_card($message_id);

        // 判定：卡存在（串流新制）→ 卡 flush 入 DB（帶 response_id——檔案同 DB id 一致）
        if ($message_card !== null) {
            sc_ai_chats()->message()->card()->flush_message_card($message_card, $status);
        } else {
            // 舊制（冇卡）——chat_row 由 chat 域帶入（upsert 讀檔補正＋段收邊）
            $chat_row          = sc_ai_chats()->chat()->get_chat_row($chat_id);
            $_polling_job_item = ["message_id" => $message_id, "status" => $status];
            sc_ai_chats()->message()->repo_update()->upsert_db_message_response($chat_row, $_polling_job_item);

            // 段行收邊保險（upsert 讀唔到嘅殘留 poll 段行——檔案缺失等；按終態一律收）
            sc_ai_chats()->message()->repo_update()->close_db_message_response_rows($message_id, $status);
        }

        // ③ user 列標真實 status（content 不動——保持既有內容；四桶計數唯一入口）
        sc_ai_chats()->message()->repo_update()->update_db_message_status($message_id, $status);

        // ④ 寫入歷史檔（append JSONL ＋ 產生 meta——同樣必須喺移檔之前）
        sc_ai_chats()->chat()->chat_history_update($chat_id, $message_id);

        // ⑤ 歸檔（整個目錄 polling/ → archive/——最後一步：之後冇得再讀檔）
        sc_ai_chats()->message()->archive_msg_files($chat_id, $message_id);

        // ⑥ 卡檔刪除（flush 完成——DB 已係真相）
        sc_ai_chats()->message()->card()->delete_message_card($message_id);
    }

}

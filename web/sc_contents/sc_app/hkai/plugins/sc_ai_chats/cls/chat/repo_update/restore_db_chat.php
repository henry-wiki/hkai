<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_update;

// == sc_ai_chats chat restore_db_chat：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-10-04 還原（chat_status＝normal——歸檔嘅鏡像；對照 archive_db_chat 而寫） ==

trait restore_db_chat
{
    // 還原對話（chat_status＝normal＋chat_updated_at；之後 refresh 專案統計）
    public function restore_db_chat($chat_id)
    {
        $connection = sc_db()->connection("sqlite");

        $connection->statement(
            "UPDATE chats SET chat_status = 'normal', chat_updated_at = ? WHERE chat_id = ?",
            [date("Y-m-d H:i:s"), (int)$chat_id]
        );

        // 上游（專案）時間／計數同步
        $chat_row = sc_ai_chats()->chat()->get_chat_row((int)$chat_id);

        // 判定：有專案 → refresh
        if ($chat_row !== null) {
            sc_ai_projects()->project()->refresh_project_stats((int)$chat_row["project_id"]);
        }

        // 對話狀態改動 → 通知監視（chat gate）
    }

}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_update;

// == sc_ai_chats chat restore_db_chat：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-10-04 還原（chat_status＝normal——歸檔嘅鏡像；對照 archive_db_chat 而寫） ==

trait restore_db_chat
{
    // 還原對話（chats chat_status＝normal——唔碰訊息／檔案／時間）
    public function restore_db_chat($chat_id)
    {
        $connection = sc_db()->connection("sqlite");

        $connection->statement(
            "UPDATE chats SET chat_status = 'normal' WHERE chat_id = ?",
            [$chat_id]
        );
    }

}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_update;

// == sc_ai_chats chat archive_db_chat：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/repo_update.php 分獨立檔（one file one function） ==

trait archive_db_chat
{
    // 歸檔對話（chats chat_status＝archived——唔碰訊息／檔案／時間）
    public function archive_db_chat($chat_id)
    {
        $connection = sc_db()->connection("sqlite");

        $connection->statement(
            "UPDATE chats SET chat_status = 'archived' WHERE chat_id = ?",
            [$chat_id]
        );
    }

}

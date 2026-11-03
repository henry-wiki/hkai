<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_update;

// == sc_ai_chats chat archive_db_chat：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_chats/cls/chat/repo_update/ 分獨立檔（one file one function） ==

trait archive_db_chat
{
    // 歸檔對話（chat_status＝archived＋chat_updated_at；之後 refresh 專案統計）
    public function archive_db_chat($chat_id)
    {
        $connection = sc_db()->connection("sqlite");

        $connection->statement(
            "UPDATE chats SET chat_status = 'archived', chat_updated_at = ? WHERE chat_id = ?",
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

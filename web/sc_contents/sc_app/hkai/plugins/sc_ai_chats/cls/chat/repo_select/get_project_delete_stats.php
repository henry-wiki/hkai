<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_select;

// == sc_ai_chats chat get_project_delete_stats：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==

trait get_project_delete_stats
{

    // 刪除確認統計（危險操作前現況——該專案會一併消失嘅資料：對話總數／已歸檔對話數／訊息總數）
    public function get_project_delete_stats($project_id)
    {
        $connection = sc_db()->connection("sqlite");

        $chat_row = $connection->selectOne(
            "SELECT COUNT(*) AS count_value FROM chats WHERE project_id = ?",
            [$project_id]
        );

        $archived_chat_row = $connection->selectOne(
            "SELECT COUNT(*) AS count_value FROM chats WHERE project_id = ? AND chat_status = 'archived'",
            [$project_id]
        );

        $message_row = $connection->selectOne(
            "SELECT COUNT(*) AS count_value FROM messages WHERE project_id = ?",
            [$project_id]
        );

        return [
            "chat_count"          => (int)$chat_row["count_value"],
            "archived_chat_count" => (int)$archived_chat_row["count_value"],
            "message_count"       => (int)$message_row["count_value"],
        ];
    }

}

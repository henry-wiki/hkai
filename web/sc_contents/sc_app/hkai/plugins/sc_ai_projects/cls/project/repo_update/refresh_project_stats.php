<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_update;

// == sc_ai_projects project refresh_project_stats：project 域動作（trait——掛入 project_Class；檔＝動作）
// == 職責：由 chats／messages／message_response 重算專案統計欄（chat_count／message_count／response_count／chat_updated_at）

trait refresh_project_stats
{
    // 重算專案統計（單條 UPDATE——計數以 DB 為真相）
    public function refresh_project_stats($project_id)
    {
        $refresh_sql = "UPDATE projects SET "
            . "chat_count = (SELECT COUNT(*) FROM chats c WHERE c.project_id = projects.project_id), "
            . "message_count = (SELECT COUNT(*) FROM messages m WHERE m.project_id = projects.project_id), "
            . "response_count = (SELECT COUNT(*) FROM message_response r WHERE r.project_id = projects.project_id), "
            . "chat_updated_at = COALESCE((SELECT MAX(c.chat_updated_at) FROM chats c WHERE c.project_id = projects.project_id), '') "
            . "WHERE project_id = ?";

        sc_db()->connection("sqlite")->affectingStatement($refresh_sql, [(int)$project_id]);
    }
}

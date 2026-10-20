<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_update;

// == sc_ai_projects project delete_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_repo_update.php 分遷入（one file one action） ==

trait delete_project
{
    public function delete_project($project_id)
    {
        $db = sc_db()->connection("sqlite");

        $db->affectingStatement("DELETE FROM message_response WHERE project_id = ?", [$project_id]);
        $db->affectingStatement("DELETE FROM messages WHERE project_id = ?", [$project_id]);
        $db->affectingStatement("DELETE FROM chats WHERE project_id = ?", [$project_id]);
        $db->affectingStatement("DELETE FROM projects WHERE project_id = ?", [$project_id]);
    }
}

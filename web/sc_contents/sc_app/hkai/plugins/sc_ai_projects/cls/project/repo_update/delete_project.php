<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_update;

// == sc_ai_projects project delete_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_repo_update.php 分遷入（one file one action） ==

trait delete_project
{
    public function delete_project($project_id)
    {
        // 判定：Home 專案（slug＝home）→ 拒絕（用戶不可改）
        $target_project_row = sc_db()->selectOne("SELECT project_slug FROM projects WHERE project_id = ?", [(int)$project_id]);

        // 判定：Home → 唔准改
        if ($target_project_row !== null && $target_project_row["project_slug"] === sc_get_config("hkai.default_project_slug")) {
            return false;
        }

        // 專案附件（DB 列＋磁盤檔——不等 message row 存在）
        sc_ai_chats()->attachment()->delete_by_project($project_id);

        $db = sc_db()->connection("sqlite");

        $db->affectingStatement("DELETE FROM message_response WHERE project_id = ?", [$project_id]);
        $db->affectingStatement("DELETE FROM messages WHERE project_id = ?", [$project_id]);
        $db->affectingStatement("DELETE FROM chats WHERE project_id = ?", [$project_id]);
        $db->affectingStatement("DELETE FROM projects WHERE project_id = ?", [$project_id]);
    }
}

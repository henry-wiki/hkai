<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_update;

// == sc_ai_projects project update_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_repo_update.php 分遷入（one file one action） ==

trait update_project
{
    public function update_project($project_id, $project_datapairs)
    {
        $db = sc_db()->connection("sqlite");

        list($update_sql, $update_bindings) = $db->update_sql("projects", $project_datapairs, "project_id = ?", [$project_id]);
        $db->affectingStatement($update_sql, $update_bindings);
    }
}

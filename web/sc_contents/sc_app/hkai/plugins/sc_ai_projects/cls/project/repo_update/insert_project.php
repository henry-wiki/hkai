<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\repo_update;

// == sc_ai_projects project insert_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_repo_update.php 分遷入（one file one action） ==

trait insert_project
{
    public function insert_project($project_datapairs)
    {
        $db = sc_db()->connection("sqlite");

        list($insert_sql, $insert_bindings) = $db->insert_sql("projects", $project_datapairs);
        $db->insert($insert_sql, $insert_bindings);
    }
}

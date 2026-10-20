<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\resolver;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects project get_latest_activity_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_resolver.php 分遷入（one file one action） ==

trait get_latest_activity_project
{
    public function get_latest_activity_project()
    {
        $latest_activity_row = hkai__sc_page_activity()->get_latest_activity_row();

        // 無任何前往記錄 → null
        if (!$latest_activity_row) {
            return null;
        }

        // 該筆冇專案（純對話前往）→ null
        $project_id = (int)$latest_activity_row["project_id"];

        if (!$project_id) {
            return null;
        }

        return repo_select()->get_project_row($project_id);
    }
}

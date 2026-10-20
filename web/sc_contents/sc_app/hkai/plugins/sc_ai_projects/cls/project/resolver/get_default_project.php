<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\resolver;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects project get_default_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_resolver.php 分遷入（one file one action） ==

trait get_default_project
{
    public function get_default_project()
    {
        $project_rows = repo_select()->get_project_rows();

        // 判定：完全冇專案 → null
        if (!$project_rows) {
            return null;
        }

        $first_project_row = $project_rows[0];

        return $first_project_row;
    }
}

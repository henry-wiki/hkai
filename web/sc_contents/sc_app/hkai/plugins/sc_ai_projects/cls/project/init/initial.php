<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\init;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects project initial：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_init.php 分遷入（one file one action） ==

trait initial
{
    public function initial()
    {
        // 查表全集＝未歸檔列＋歸檔列（查表／slug 佔用判定用——歸檔專案照可查；清單顯示另經各自 SQL 過濾）
        $project_rows          = repo_select()->get_project_rows();
        $archived_project_rows = repo_select()->get_archived_project_rows();
        $all_project_rows      = array_merge($project_rows, $archived_project_rows);

        $project_rows_by_id   = array_group_by($all_project_rows,"project_id",true);
        $project_rows_by_slug = array_group_by($all_project_rows,"project_slug",true);

        $initial_data = [];

        $initial_data["projects"]               = $all_project_rows;
        $initial_data["project_rows_by_id"]     = $project_rows_by_id;
        $initial_data["project_rows_by_slug"]   = $project_rows_by_slug;

        $this->initial_data = $initial_data;
    }
}

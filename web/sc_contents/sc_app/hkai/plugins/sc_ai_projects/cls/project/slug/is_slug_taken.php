<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\slug;

// == sc_ai_projects project is_slug_taken：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_slug.php 分遷入（one file one action） ==

trait is_slug_taken
{
    public function is_slug_taken($project_slug, $exclude_project_id = 0)
    {
        // 保留字 → 視為已佔用
        if ($this->is_reserved_slug($project_slug) === true) {
            return true;
        }

        $project_row = sc_ai_projects()->project()->get_project_row($project_slug);

        // 無此 slug → 未佔用
        if ($project_row === null) {
            return false;
        }

        // 佔用者係自己 → 未佔用
        if ((int)$project_row["project_id"] === (int)$exclude_project_id) {
            return false;
        }

        return true;
    }
}

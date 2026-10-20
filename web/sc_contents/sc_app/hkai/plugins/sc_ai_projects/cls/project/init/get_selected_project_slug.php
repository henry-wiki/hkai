<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\init;

// == sc_ai_projects project get_selected_project_slug：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_init.php 分遷入（one file one action） ==

trait get_selected_project_slug
{
    public function get_selected_project_slug()
    {
        $requested_project_key = sc_get_friendly(0);

        // URL 未指定專案段（根 URL）→ 無選取專案
        if ($requested_project_key === null) {
            return null;
        }

        // 查表用純 slug（歸檔語境形 archive@<project_slug> → @ 後；其餘 → 原值）
        $lookup_project_slug = $this->get_bare_project_slug($requested_project_key);

        // URL 有指定且有效 → 採用（回純 slug）
        if ($this->has_project($lookup_project_slug)) {
            return $lookup_project_slug;
        }

        return null;
    }
}

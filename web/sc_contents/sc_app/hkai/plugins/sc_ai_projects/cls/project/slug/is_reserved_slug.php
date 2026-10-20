<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\slug;

// == sc_ai_projects project is_reserved_slug：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_slug.php 分遷入（one file one action） ==

trait is_reserved_slug
{
    public function is_reserved_slug($project_slug)
    {
        return in_array($project_slug, $this->reserved_slugs, true);
    }
}

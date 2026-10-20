<?php

namespace sc\app\hkai\plugins\sc_ai_projects\project\init;

use function sc\app\hkai\plugins\sc_ai_projects\repo_select;

// == sc_ai_projects project has_project：project 域動作（trait——掛入 project_Class；檔＝動作） ==
// == 2026-09-27 自 sc_ai_projects_init.php 分遷入（one file one action） ==

trait has_project
{
    public function has_project($needle)
    {
        return repo_select()->get_project_row($needle) !== null;
    }
}

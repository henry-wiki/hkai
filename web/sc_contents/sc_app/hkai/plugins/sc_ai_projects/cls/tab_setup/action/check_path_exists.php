<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup check_path_exists：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/checks.php 分遷入（one file one action） ==

trait check_path_exists
{
    public function check_path_exists($project_path, $description)
    {
        return is_dir($project_path);
    }
}

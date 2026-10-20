<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup check_has_gitignore：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/checks.php 分遷入（one file one action） ==

trait check_has_gitignore
{
    public function check_has_gitignore($project_path, $description)
    {
        return is_file($project_path . "/.gitignore");
    }
}

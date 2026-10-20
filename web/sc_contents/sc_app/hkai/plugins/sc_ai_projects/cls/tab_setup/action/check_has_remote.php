<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup check_has_remote：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/checks.php 分遷入（one file one action） ==

trait check_has_remote
{
    public function check_has_remote($project_path, $description)
    {
        $config_file  = $project_path . "/.git/config";

        // 無 config → 尚未初始化
        if (!is_file($config_file)) {
            return false;
        }

        $config_content = (string)@file_get_contents($config_file);

        // 找 [remote "origin"] 區段標頭
        return preg_match('/^\s*\[remote\s+"origin"\]/m', $config_content) === 1;
    }
}

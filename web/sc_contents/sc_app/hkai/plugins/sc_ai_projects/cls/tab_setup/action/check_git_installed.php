<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\action;

// == sc_ai_projects tab_setup check_git_installed：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/checks.php 分遷入（one file one action） ==

trait check_git_installed
{
    public function check_git_installed($project_path, $description)
    {
        // 已有快取 → 直接回傳（不重複跑子程序）
        if (self::$git_installed_cache !== null) {
            return self::$git_installed_cache;
        }

        $command_output = [];
        $command_result = 0;
        @exec("git --version 2>&1", $command_output, $command_result);

        self::$git_installed_cache = ($command_result === 0);

        return self::$git_installed_cache;
    }
}

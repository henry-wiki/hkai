<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_row：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_row
{
    private function get_git_row($git_key, $git_value)
    {
        $outputLines   = [];
        $outputLines[] = "<div class='git_row'>";
            $outputLines[] = "<span class='git_key'>" . $git_key . "</span>";
            $outputLines[] = "<span class='git_val'>" . sc_html_encode($git_value, ENT_QUOTES) . "</span>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}

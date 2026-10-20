<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git build_notice_pane：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait build_notice_pane
{
    private function build_notice_pane($notice_text)
    {
        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='git'>";
        $outputLines[] = "<div class='git'>";
        $outputLines[] = sc_empty_state($notice_text, false);
        $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}

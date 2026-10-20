<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\view;

// == sc_ai_projects tab_rule build_notice_pane：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==
// == 2026-09-27 自 tab_rule/view.php 分遷入（one file one action） ==

trait build_notice_pane
{
    private function build_notice_pane($notice_text)
    {
        $outputLines   = [];
        $outputLines[] = "<div class='modal_pane on' data-pane='rule'>";
            $outputLines[] = "<div class='pane_body'>";
                $outputLines[] = sc_empty_state($notice_text, false);
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}

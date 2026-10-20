<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_commit：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_commit
{
    private function get_git_commit($commit_row)
    {
        $commit_time = date("Y-m-d H:i", strtotime($commit_row["committed_at"]));

        $outputLines   = [];
        $outputLines[] = "<div class='check_item'>";
            $outputLines[] = "<div class='cinfo'>";
                $outputLines[] = "<div class='cname'>" . sc_html_encode($commit_row["subject"], ENT_QUOTES) . "</div>";
                $outputLines[] = "<div class='cdesc'>" . $commit_row["sha"] . " · " . $commit_time . "</div>";
            $outputLines[] = "</div>";
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}

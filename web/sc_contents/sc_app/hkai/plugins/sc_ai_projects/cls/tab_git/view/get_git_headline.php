<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_headline：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_headline
{
    private function get_git_headline($branch_text, $git_status)
    {
        $git_changes = $git_status["changes"];

        $state_class = "clean";
        $state_text  = hkai__i18n()->get("git.clean");

        // 判定：有未提交變更 → dirty
        if ($git_changes["total"] > 0) {
            $state_class = "dirty";
            $state_text  = $git_changes["total"] . " " . hkai__i18n()->get("git.unit_files");
        }

        // 判定：有衝突 → conflict（覆蓋 dirty）
        if ($git_changes["conflicts"] > 0) {
            $state_class = "conflict";
            $state_text  = $git_changes["conflicts"] . " " . hkai__i18n()->get("git.unit_conflicts");
        }

        $outputLines   = [];
        $outputLines[] = "<div class='git_headline " . $state_class . "'>";
            $outputLines[] = "<span class='git_headline_branch'>⎇ " . sc_html_encode($branch_text, ENT_QUOTES) . "</span>";
            $outputLines[] = "<span class='git_headline_state'>" . $state_text . "</span>";

            // 判定：有行數變動 → 顯示 +/−
            if ($git_status["diff"] !== null) {
                $diff_total = $git_status["diff"]["additions"] + $git_status["diff"]["deletions"];

                // 判定：行數有變 → 輸出
                if ($diff_total > 0) {
                    $outputLines[] = "<span class='git_headline_diff'>+" . $git_status["diff"]["additions"]
                        . " −" . $git_status["diff"]["deletions"] . "</span>";
                }
            }
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git get_git_change_text：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait get_git_change_text
{
    private function get_git_change_text($git_status)
    {
        $git_changes = $git_status["changes"];
        $change_text = "乾淨（無未提交變更）";

        // 判定：有變更 → 組詳細文字
        if ($git_changes["total"] > 0) {
            $change_text = $git_changes["total"] . " 個檔案（staged " . $git_changes["staged"]
                . "／unstaged " . $git_changes["unstaged"] . "／untracked " . $git_changes["untracked"] . "）";

            // 判定：有追蹤檔改動 → 附 +/− 行數
            if ($git_status["diff"] !== null) {
                $change_text .= " ＋" . $git_status["diff"]["additions"] . " −" . $git_status["diff"]["deletions"];
            }

            // 判定：有衝突 → 標示
            if ($git_changes["conflicts"] > 0) {
                $change_text .= "；⚠ 衝突 " . $git_changes["conflicts"];
            }
        }

        return $change_text;
    }
}

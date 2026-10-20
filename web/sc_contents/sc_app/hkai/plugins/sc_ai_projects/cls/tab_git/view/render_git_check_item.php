<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git render_git_check_item：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 結構同 check_item 卡（✓／✕ 記號＋名稱＋說明＋可選右側動作掣——樣式 .check_item 系共用） ==

trait render_git_check_item
{
    private function render_git_check_item($git_check_label, $git_check_desc, $git_check_passed, $git_check_action_html = "")
    {
        // 通過／未通過 → 樣式與符號
        $item_class = "check_item fail";
        $mark_text  = "✕";

        // 判定：通過 → pass 樣式＋✓
        if ($git_check_passed === true) {
            $item_class = "check_item pass";
            $mark_text  = "✓";
        }

        $outputLines   = [];
        $outputLines[] = "<div class='" . $item_class . "'>";
            $outputLines[] = "<div class='mark'>" . $mark_text . "</div>";
            $outputLines[] = "<div class='cinfo'>";
                $outputLines[] = "<div class='cname'>" . $git_check_label . "</div>";
                $outputLines[] = "<div class='cdesc'>" . sc_html_encode($git_check_desc, ENT_QUOTES) . "</div>";
            $outputLines[] = "</div>";

            // 判定：有動作掣 → 附右側掣（未準備→Stage；已準備→Commit）
            if ($git_check_action_html !== "") {
                $outputLines[] = $git_check_action_html;
            }
        $outputLines[] = "</div>";

        return implode("\r\n", $outputLines);
    }
}

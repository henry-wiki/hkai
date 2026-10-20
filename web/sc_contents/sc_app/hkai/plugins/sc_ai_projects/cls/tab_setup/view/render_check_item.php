<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\view;

// == sc_ai_projects tab_setup render_check_item：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/view.php 分遷入（one file one action） ==

trait render_check_item
{
    private function render_check_item($check_item)
    {
        // 通過／未通過 → 樣式與符號
        $item_class = "check_item fail";
        $mark_text  = "✕";
        if ($check_item["passed"] === true) {
            $item_class = "check_item pass";
            $mark_text  = "✓";
        }
        
        $outputLines   = [];
        $outputLines[] = "<div class='" . $item_class . "' data-check_key='" . $check_item["key"] . "'>";
        $outputLines[] = "<div class='mark'>" . $mark_text . "</div>";
        $outputLines[] = "<div class='cinfo'>";
            $outputLines[] = "<div class='cname'>" . $check_item["label"] . "<span class='scope'>" . $check_item["scope"] . "</span></div>";
            $outputLines[] = "<div class='cdesc'>" . $check_item["hint"] . "</div>";
        $outputLines[] = "</div>";
        $outputLines[] = "</div>";
        
        return implode("\r\n", $outputLines);
    }
}

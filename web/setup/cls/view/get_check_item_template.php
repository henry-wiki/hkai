<?php

namespace sc\setup\view;

// == HKAI setup view get_check_item_template：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：檢查項空殼（init 態——唔跑檢查、只出位；交前端逐項查、換真卡）

trait get_check_item_template
{
    public function get_check_item_template($check_item_code)
    {
        $check_item_code_attr = htmlspecialchars($check_item_code, ENT_QUOTES);

        $output_lines   = [];
        $output_lines[] = "<div class='check_item' data-status='init' data-check_item='" . $check_item_code_attr . "'>";
        $output_lines[] = "<div class='mark'></div>";
        $output_lines[] = "<div class='cinfo'>";
        $output_lines[] = "<div class='cname'></div>";
        $output_lines[] = "</div>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}

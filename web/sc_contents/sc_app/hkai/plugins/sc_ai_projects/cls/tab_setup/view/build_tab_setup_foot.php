<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\view;

// == sc_ai_projects tab_setup build_tab_setup_foot：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/view.php 分遷入（one file one action） ==

trait build_tab_setup_foot
{
    public function build_tab_setup_foot()
    {
        // 取消鈕（各 tab 皆有——關閉視窗 affordance）
        $cancel_wrapper   = [];
        $cancel_wrapper[] = "<div class='sc_btn_wrapper'>";
        $cancel_wrapper[] = "<button class='project_modal_btn btn_item' data-btn='project_cancel'>取消</button>";
        $cancel_wrapper[] = "</div>";

        return implode("\r\n", $cancel_wrapper);
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git\view;

// == sc_ai_projects tab_git build_tab_git_foot：tab_git 域動作（trait——掛入 tab_git_Class；檔＝動作） ==
// == 2026-09-27 自 tab_git/view.php 分遷入（one file one action） ==

trait build_tab_git_foot
{
    public function build_tab_git_foot()
    {
        // 取消鈕（各 tab 皆有——關閉視窗 affordance）
        $cancel_label = hkai__i18n()->get("common.cancel");

        $cancel_wrapper   = [];
        $cancel_wrapper[] = "<div class='sc_btn_wrapper'>";
        $cancel_wrapper[] = "<button class='project_modal_btn btn_item' data-btn='project_cancel'>" . $cancel_label . "</button>";
        $cancel_wrapper[] = "</div>";

        return implode("\r\n", $cancel_wrapper);
    }
}

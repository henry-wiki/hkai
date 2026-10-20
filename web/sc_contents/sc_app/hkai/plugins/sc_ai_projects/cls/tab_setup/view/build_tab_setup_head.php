<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup\view;

// == sc_ai_projects tab_setup build_tab_setup_head：tab_setup 域動作（trait——掛入 tab_setup_Class；檔＝動作） ==
// == 2026-09-27 自 tab_setup/view.php 分遷入（one file one action） ==

trait build_tab_setup_head
{
    public function build_tab_setup_head($is_edit)
    {
        $modal_title = "新增專案";

        // 編輯模式 → 標題改「編輯專案」
        if ($is_edit === true) {
            $modal_title = "編輯專案";
        }

        return $modal_title;
    }
}

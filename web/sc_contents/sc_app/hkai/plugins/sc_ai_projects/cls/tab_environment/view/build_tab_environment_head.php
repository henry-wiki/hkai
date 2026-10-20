<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment\view;

// == sc_ai_projects tab_environment build_tab_environment_head：tab_environment 域動作（trait——掛入 tab_environment_Class；檔＝動作） ==

trait build_tab_environment_head
{
    public function build_tab_environment_head($is_edit)
    {
        $modal_title = "新增專案";

        // 編輯模式 → 標題改「編輯專案」
        if ($is_edit === true) {
            $modal_title = "編輯專案";
        }

        return $modal_title;
    }
}

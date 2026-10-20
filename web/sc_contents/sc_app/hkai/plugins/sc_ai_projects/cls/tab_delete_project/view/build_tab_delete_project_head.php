<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_delete_project\view;

// == sc_ai_projects tab_delete_project build_tab_delete_project_head：tab_delete_project 域動作（trait——掛入 tab_delete_project_Class；檔＝動作） ==

trait build_tab_delete_project_head
{
    public function build_tab_delete_project_head($is_edit)
    {
        $modal_title = "新增專案";

        // 編輯模式 → 標題改「編輯專案」
        if ($is_edit === true) {
            $modal_title = "編輯專案";
        }

        return $modal_title;
    }
}

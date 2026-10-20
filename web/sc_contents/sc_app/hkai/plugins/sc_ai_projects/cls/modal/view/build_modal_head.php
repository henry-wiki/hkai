<?php

namespace sc\app\hkai\plugins\sc_ai_projects\modal\view;

// == sc_ai_projects modal build_modal_head：modal 域動作（trait——掛入 modal_Class；檔＝動作） ==
// == 2026-09-27 自 modal/view.php 分遷入（one file one action） ==

trait build_modal_head
{
    public function build_modal_head($is_edit)
    {
        $modal_title = "新增專案";
        
        // 編輯模式 → 標題改「編輯專案」
        if ($is_edit === true) {
            $modal_title = "編輯專案";
        }
        
        return $modal_title;
    }
}

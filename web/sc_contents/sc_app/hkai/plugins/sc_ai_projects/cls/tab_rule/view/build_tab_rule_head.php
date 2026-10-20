<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule\view;

// == sc_ai_projects tab_rule build_tab_rule_head：tab_rule 域動作（trait——掛入 tab_rule_Class；檔＝動作） ==
// == 2026-09-27 自 tab_rule/view.php 分遷入（one file one action） ==

trait build_tab_rule_head
{
    public function build_tab_rule_head($is_edit)
    {
        $modal_title = "新增專案";

        // 編輯模式 → 標題改「編輯專案」
        if ($is_edit === true) {
            $modal_title = "編輯專案";
        }

        return $modal_title;
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_projects\modal\tabs;

// == sc_ai_projects modal get_modal_tab：modal 域動作（trait——掛入 modal_Class；檔＝動作） ==
// == 2026-09-27 自 modal/tabs.php 分遷入（one file one action） ==

trait get_modal_tab
{
    public function get_modal_tab($tab_id)
    {
        $modal_tabs = $this->get_modal_tabs();

        // 未指定 tab_id → 回第一個（預設 tab）
        if ($tab_id === "") {
            return $modal_tabs[0];
        }

        // 逐筆比對 tab_id
        foreach ($modal_tabs as $tab_item) {
            $item_tab_id = $tab_item["tab_id"];

            // tab_id 相同 → 回該 tab
            if ($item_tab_id === $tab_id) {
                return $tab_item;
            }
        }

        return null;
    }
}

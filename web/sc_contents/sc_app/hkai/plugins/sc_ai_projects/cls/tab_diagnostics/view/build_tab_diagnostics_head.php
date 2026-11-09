<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\view;

// == sc_ai_projects tab_diagnostics build_tab_diagnostics_head：tab_diagnostics 域動作（trait——掛入 tab_diagnostics_Class；檔＝動作） ==

trait build_tab_diagnostics_head
{
    public function build_tab_diagnostics_head($is_edit)
    {
        $modal_title = hkai()->i18n()->get("project.modal.add_title");

        // 編輯模式 → 標題改「編輯專案」
        if ($is_edit === true) {
            $modal_title = hkai()->i18n()->get("project.modal.edit_title");
        }

        return $modal_title;
    }
}

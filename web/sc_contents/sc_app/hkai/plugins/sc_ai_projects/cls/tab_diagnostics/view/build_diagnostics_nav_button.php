<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\view;

// == sc_ai_projects tab_diagnostics build_diagnostics_nav_button：tab_diagnostics 域動作（trait——掛入 tab_diagnostics_Class；檔＝動作） ==
// == 2026-10-04 立（左側 tab 化——單項按鈕；class 用 settings_nav_button——1:1 跟 sc_ai_settings） ==

trait build_diagnostics_nav_button
{
    // 單一 section 的按鈕 HTML（data-section 供切換；首項＝預設 active）
    private function build_diagnostics_nav_button($diagnostics_item, $is_active)
    {
        $button_class = "settings_nav_button";

        // 判定：預設項 → 附 active
        if ($is_active === true) {
            $button_class .= " active";
        }

        return "<button type='button' class='" . $button_class . "' data-section='" . $diagnostics_item["id"] . "'>" . $diagnostics_item["name"] . "</button>";
    }
}

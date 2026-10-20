<?php

namespace sc\app\hkai\plugins\sc_ai_settings\settings\menu;

// == sc_ai_settings settings handle_fetch_settings：settings 域動作（trait——掛入 settings_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat request_Class 分遷入（one file one action） ==

trait handle_fetch_settings
{
    // GET fetch_settings handler（side menu 動態載入——回 initial data；直接 return json）
    public function handle_fetch_settings()
    {
        $setting_sections = $this->get_initial_data("setting_sections");

        $navigation = [];
        $navigation[] = "<div class='modal__search_wrap'>";
            $navigation[] = "<i class='fa-solid fa-magnifying-glass modal__search_icon'></i>";
            $navigation[] = "<input type='text' class='modal__search_input' placeholder='Search settings...' />";
        $navigation[] = "</div>";
        $navigation[] = "<div class='modal__navigation-content'>";
            foreach ($setting_sections as $setting_item) {
                $navigation[] = $this->build_nav_section_html($setting_item);
            }
        $navigation[] = "</div>";
        $html = ["navigation" => implode("\r\n", $navigation)];

        return sc_response()->json(["ok" => true, "setting_sections" => $setting_sections, "html" => $html]);
    }
}

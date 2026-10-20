<?php

namespace sc\setup\view;

// == HKAI setup view build_modal_left：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：modal 左——六格 nav 一覽（ul>li>div；次序＝註冊表；data-setup_section＝格身份；active＝傳入目前格）

trait build_modal_left
{
    // 出 modal 左（nav 一大件——次序＝註冊表）
    public function build_modal_left($sections, $active_section_id)
    {
        // 語系（nav 名跟當前語系——解析喺本動作）
        $current_language = setup()->i18n()->get_lang_code();

        $modal_left   = [];
        $modal_left[] = "<div class='setup_modal__left'>";
        $modal_left[] = "<ul class='setup_nav_list'>";

        // 逐格出 nav item
        foreach ($sections as $section_id => $section_item) {
            $section_name_map = $section_item["name"];

            // 判定：當前語系有名 → 用嗰個
            if (isset($section_name_map[$current_language])) {
                $section_name = $section_name_map[$current_language];
            }
            // 其餘（冇嗰語翻譯）→ 第一項
            else {
                $name_values  = array_values($section_name_map);
                $section_name = $name_values[0];
            }

            $section_name = htmlspecialchars($section_name, ENT_QUOTES);

            $active_class = "";

            // 判定：目前格 → active
            if ($section_id === $active_section_id) {
                $active_class = " is-active";
            }

            $modal_left[] = "<li>";
            $modal_left[] = "<div class='setup_nav_button" . $active_class . "' data-setup_section='" . $section_id . "'>" . $section_name . "</div>";
            $modal_left[] = "</li>";
        }

        $modal_left[] = "</ul>";
        $modal_left[] = "</div>";

        return $modal_left;
    }
}

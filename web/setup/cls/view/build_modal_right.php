<?php

namespace sc\setup\view;

// == HKAI setup view build_modal_right：view 域動作（trait——掛入 view_Class；檔＝動作）
// == 職責：modal 右——外殼（右欄包裝＋區塊殼；內容全部由格單元 build_section 出——外殼只負責展示）

trait build_modal_right
{
    // 出 modal 右（外殼——右欄包裝＋單格殼＋嵌格單元整件）
    public function build_modal_right($sections, $active_section_id)
    {
        // 判定：welcome 格 → 用 welcome 單元
        if ($active_section_id === "welcome") {
            $section_instance = setup()->sections()->welcome();
            $section_content  = $section_instance->build_section();
        }
        // 判定：prerequisite 格 → 用 prerequisite 單元
        elseif ($active_section_id === "prerequisite") {
            $section_instance = setup()->sections()->prerequisite();
            $section_content  = $section_instance->build_section();
        }
        // 判定：database 格 → 用 database 單元
        elseif ($active_section_id === "database") {
            $section_instance = setup()->sections()->database();
            $section_content  = $section_instance->build_section();
        }
        // 判定：settings 格 → 用 settings 單元
        elseif ($active_section_id === "settings") {
            $section_instance = setup()->sections()->setting();
            $section_content  = $section_instance->build_section();
        }
        // 判定：install 格 → 用 install 單元
        elseif ($active_section_id === "install") {
            $section_instance = setup()->sections()->install();
            $section_content  = $section_instance->build_section();
        }
        // 判定：completed 格 → 用 completed 單元
        elseif ($active_section_id === "completed") {
            $section_instance = setup()->sections()->completed();
            $section_content  = $section_instance->build_section();
        }
        // 其餘（理論唔到——註冊表先驗）→ 退回 welcome
        else {
            $section_instance = setup()->sections()->welcome();
            $section_content  = $section_instance->build_section();
        }

        $modal_right   = [];
        $modal_right[] = "<div class='setup_modal__right'>";
        $modal_right[] = "<div class='setup_section is-active' data-setup_section='" . $active_section_id . "'>";
        
        // 逐行入列（一行一動作——統一 $modal_right[] 形）
        foreach ($section_content as $section_line_item) {
            $modal_right[] = $section_line_item;
        }
        
        $modal_right[] = "</div>";
        $modal_right[] = "</div>";
        
        return $modal_right;
    }
}

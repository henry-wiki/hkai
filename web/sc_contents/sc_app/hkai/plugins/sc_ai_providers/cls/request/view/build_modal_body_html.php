<?php

namespace sc\app\hkai\plugins\sc_ai_providers\request\view;

// == sc_ai_providers request view build_modal_body_html：request 域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-10-06 自 request/_.bridge.cls.php 分遷入（one file one action） ==

trait build_modal_body_html
{
    // 砌 modal body HTML（左側欄（搜尋框＋provider 清單）＋右內容（單一內容位）——後端砌，前端只 insert）
    private function build_modal_body_html($available_providers)
    {
        $output_lines = [];
        $output_lines[] = "<div class='modal__navigation'>";
        $output_lines[] = "<div class='modal__search_wrap'>";
        $output_lines[] = "<i class='fa-solid fa-magnifying-glass modal__search_icon'></i>";
        $output_lines[] = "<input id='model_selector_search' class='modal__search_input' type='text' placeholder='search / provider filtering'>";
        $output_lines[] = "</div>";
        $output_lines[] = "<div id='side_provider_list' class='modal__navigation-content'>";

        // 逐 provider 一粒側欄掣（名由可用 provider 聲明取）
        foreach ($available_providers as $provider_id => $provider_item) {
            $provider_name = $provider_item["provider_name"];
            $output_lines[] = "<div class='side_provider_item'>";
            $output_lines[] = "<button class='side_provider_tab_button' data-provider_id='" . $provider_id . "'>" . $provider_name . "</button>";
            $output_lines[] = "</div>";
        }

        $output_lines[] = "</div>";
        $output_lines[] = "</div>";
        $output_lines[] = "<div class='modal__content'>";
        $output_lines[] = "<div id='model_selector_table_wrap' class='model_selector_table_wrap'></div>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}

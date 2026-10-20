<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request\view;

// == sc_ai_benchmark request view build_modal_body_html：request 域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-10-07 多 entry：左欄＝預設 bench_source 嘅 entry 掣（來源切換列已升去 modal__tabs） ==
// == 2026-10-06 自 request/_.bridge.cls.php 分遷入（one file one action） ==

trait build_modal_body_html
{
    // 砌 modal body HTML（左側欄（搜尋框＋bench_source 清單）＋右內容（單一內容位）——後端砌，前端只 insert）
    private function build_modal_body_html($available_bench_source)
    {
        $output_lines = [];
        $output_lines[] = "<div class='modal__navigation'>";
        $output_lines[] = "<div class='modal__search_wrap'>";
        $output_lines[] = "<i class='fa-solid fa-magnifying-glass modal__search_icon'></i>";
        $output_lines[] = "<input id='model_selector_search' class='modal__search_input' type='text' placeholder='search / bench_source filtering'>";
        $output_lines[] = "</div>";
        $output_lines[] = "<div id='side_bench_source_list' class='modal__navigation-content'>";

        // 左欄＝預設 bench_source（第一個——開窗零記憶）嘅 entry 列；來源切換列已升去 modal__tabs
        $bench_source_ids = array_keys($available_bench_source);
        $default_bench_source_id = $bench_source_ids[0];
        $output_lines[] = $this->build_navigation_html($default_bench_source_id);

        $output_lines[] = "</div>";
        $output_lines[] = "</div>";
        $output_lines[] = "<div class='modal__content'>";
        $output_lines[] = "<div id='model_selector_table_wrap' class='model_selector_table_wrap'></div>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }
}

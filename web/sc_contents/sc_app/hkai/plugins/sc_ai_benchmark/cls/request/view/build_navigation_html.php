<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request\view;

// == sc_ai_benchmark request view build_navigation_html：request 域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-10-07 新（左欄 entry 掣砌法抽出——開窗（build_modal_body_html）同來源切換（handle_fetch_bench_source_navigation）共用） ==

trait build_navigation_html
{
    // 砌某 bench_source 嘅左欄 entry 掣 HTML（逐 navigation item 一粒；entry 名＝fetch_category 值）——後端砌，前端只 insert
    public function build_navigation_html($bench_source_id)
    {
        // 可用 bench_source 聲明（單一來源——main 域）
        $available_bench_source = sc_ai_benchmark()->main()->get_available_bench_source();
        $bench_source_item = $available_bench_source[$bench_source_id];
        $bench_source_navigation = $bench_source_item["navigation"];

        $output_lines = [];

        // 逐 navigation item 一粒掣（entry 名＝fetch_category 值）
        foreach ($bench_source_navigation as $navigation_item) {
            $fetch_category = $navigation_item["fetch_category"];
            $output_lines[] = "<div class='side_bench_source_item'>";
            $output_lines[] = "<button class='side_bench_source_tab_button' data-bench_source_id='" . $bench_source_id . "' data-entry='" . $fetch_category . "'>" . $fetch_category . "</button>";
            $output_lines[] = "</div>";
        }

        return implode("\r\n", $output_lines);
    }
}

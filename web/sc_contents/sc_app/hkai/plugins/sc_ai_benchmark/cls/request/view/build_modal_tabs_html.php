<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\request\view;

// == sc_ai_benchmark request view build_modal_tabs_html：request 域動作（trait——掛入 request_Class；檔＝動作） ==
// == 2026-10-07 新（bench_source 升上去 modal__tabs——來源切換列；第一粒預設 on 狀態） ==

trait build_modal_tabs_html
{
    // 砌 modal 分頁列 HTML（每個可用 bench_source 一粒 tab（label＝main 段 bench_source_name）；第一粒 on＝開窗預設）——後端砌，前端只 insert
    public function build_modal_tabs_html($available_bench_source)
    {
        $output_lines = [];
        $is_first = true;

        // 逐 bench_source 一粒 tab
        foreach ($available_bench_source as $bench_source_id => $bench_source_item) {
            $bench_source_main = $bench_source_item["main"];
            $bench_source_name = $bench_source_main["bench_source_name"];

            // 開窗預設＝第一粒（on——其餘等切換）
            $tab_class = "modal_tab";
            if ($is_first === true) {
                $tab_class .= " on";
                $is_first = false;
            }

            $output_lines[] = "<button type='button' class='" . $tab_class . "' data-bench_source_id='" . $bench_source_id . "'>" . $bench_source_name . "</button>";
        }

        return implode("\r\n", $output_lines);
    }
}

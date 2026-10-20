<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\arena\view;

// == sc_ai_benchmark benchmark/arena view get_model_list_page：view 動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-10-07 改為 LM Arena 排行榜顯示（rank／model／rating／vote_count；rows 原次序） ==
// == 2026-10-07 多 entry：$entry_name 對應嘅獨立 store 檔（fetch_category 值） ==
// == 細件（tbody／row）同檔——太簡單唔分檔 ==

trait get_model_list_page
{
    // 砌 bench_source 頁 HTML（大 table——bench_source 資訊列＋欄頭＋指定 entry 嘅 rows；後端砌，前端只 insert）
    public function get_model_list_page($bench_source_id, $entry_name)
    {
        // 本 entry 嘅 store 檔（合併規格列——搵返 fetch_category 對應嗰項）
        $navigation_items = bench_source()->get_bench_source_navigation_items();
        $entry_file = "";
        // 逐項搵（fetch_category 對上 = 本 entry）
        foreach ($navigation_items as $navigation_item) {
            if ($navigation_item["fetch_category"] === $entry_name) {
                $entry_file = $navigation_item["bench_source_file"];
            }
        }

        // bench_source 檔數據（包內自取——repo 子域；一次載入，讀 arena 段（rows）＋generated_at）
        $bench_source_data = bench_source()->repo()->get_bench_source_data($entry_file);
        $entry_rows = [];
        $generated_at = "";
        // 判定：bench_source 檔存在且合法 → 讀數據
        if ($bench_source_data !== null) {
            $entry_rows = $bench_source_data["arena"];
            // 判定：有 generated_at → 讀（冇 → 留空、顯示層出 N/A）
            if (isset($bench_source_data["generated_at"])) {
                $generated_at = $bench_source_data["generated_at"];
            }
        }

        // 顯示層轉義一次過（整個資料結構交 sc_html_encode——顯示層只放純變數）
        $entry_rows = sc_html_encode($entry_rows);
        $generated_at = sc_html_encode($generated_at);

        $output_lines = [];
        $output_lines[] = "<div class='model_selector_bench_source_page' data-bench_source_id='" . $bench_source_id . "' data-entry='" . $entry_name . "'>";
        $output_lines[] = "<table class='model_selector_table'>";
        $output_lines[] = $this->build_tbody_bench_source_html($bench_source_id, $generated_at);
        $output_lines[] = "<tbody data-tbody='model_list' data-bench_source_id='" . $bench_source_id . "' data-entry='" . $entry_name . "'>";

        // 無 rows（未下載／空）→ 空態提示列
        if (empty($entry_rows)) {
            $output_lines[] = "<tr class='model_selector_empty_row'><td colspan='4'>No leaderboard data — click [Refresh] to download</td></tr>";
        }
        else
        {
            // 逐 row 砌一列（次序照 API——排行榜原次序）
            foreach ($entry_rows as $entry_row_item) {
                $output_lines[] = $this->build_arena_row_html($entry_row_item);
            }
        }

        $output_lines[] = "</tbody>";
        $output_lines[] = "</table>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }

    // 砌 bench_source 資訊 tbody（搜尋框＋下載時間＋Refresh＋欄頭列）
    private function build_tbody_bench_source_html($bench_source_id, $generated_at)
    {
        // 下載時間（缺 → N/A）
        $bench_source_time_text = $generated_at ? $generated_at : "N/A";

        $output_lines = [];
        $output_lines[] = "<tbody data-tbody='bench_source' class='thead' data-bench_source_id='" . $bench_source_id . "'>";
        $output_lines[] = "<tr class='model_selector_bench_source_row' data-bench_source_id='" . $bench_source_id . "'>";
        $output_lines[] = "<td colspan='5'>";
        $output_lines[] = "<div class='model_selector_bench_source_head'>";
        $output_lines[] = "<div class='modal__search_wrap'>";
        $output_lines[] = "<i class='fa-solid fa-magnifying-glass modal__search_icon'></i>";
        $output_lines[] = "<input class='modal__search_input model_selector_table_search' type='text' placeholder='search / model filtering'>";
        $output_lines[] = "</div>";
        $output_lines[] = "<span class='model_selector_bench_source_meta'>";
        $output_lines[] = "<span class='model_selector_bench_source_time'>" . $bench_source_time_text . "</span>";
        $output_lines[] = "<button class='model_selector_bench_source_refresh' title='download leaderboard' data-bench_source_id='" . $bench_source_id . "'>Refresh</button>";
        $output_lines[] = "</span>";
        $output_lines[] = "</div>";
        $output_lines[] = "</td>";
        $output_lines[] = "</tr>";
        $output_lines[] = "<tr>";
        $output_lines[] = "<td class='model_selector_td_rank'>#</td>";
        $output_lines[] = "<td>Model</td>";
        $output_lines[] = "<td class='model_selector_td_score'>Score</td>";
        $output_lines[] = "<td class='model_selector_td_votes'>Votes</td>";
        $output_lines[] = "</tr>";
        $output_lines[] = "</tbody>";

        return implode("\r\n", $output_lines);
    }

    // 砌單一 row 列（rank＋model（兩行：名＋組織）＋分數＋票數；純顯示——rating 族／score 族兩形都食）
    private function build_arena_row_html($entry_row_item)
    {
        // 從 array 取值一律先落同名變數（顯示層只放純變數）
        $rank = $entry_row_item["rank"];
        $model_name = $entry_row_item["model_name"];
        $organization = $entry_row_item["organization"];

        // 顯示文字（rating 族（text／webdev／image／video）＝rating 取整；score 族（agent）＝score 轉百分比）
        $score_text = "";
        // 判定：有 rating → 取整
        if (isset($entry_row_item["rating"])) {
            $score_text = round($entry_row_item["rating"]);
        }
        // 判定：冇 rating 但有 score（0–1 小數——例 0.1431 → 14.31%）
        elseif (isset($entry_row_item["score"])) {
            $score_text = number_format($entry_row_item["score"] * 100, 2) . "%";
        }

        // 顯示文字（vote_count 族＝千分位；冇 → session_count 頂上；都冇 → 空）
        $votes_text = "";
        // 判定：有 vote_count → 千分位
        if (isset($entry_row_item["vote_count"])) {
            $votes_text = number_format($entry_row_item["vote_count"]);
        }
        // 判定：冇 vote_count 但有 session_count（agent）→ 千分位
        elseif (isset($entry_row_item["session_count"])) {
            $votes_text = number_format($entry_row_item["session_count"]);
        }

        $output_lines = [];
        $output_lines[] = "<tr class='model_selector_model_row'>";
        $output_lines[] = "<td class='model_selector_td_rank'>" . $rank . "</td>";
        $output_lines[] = "<td class='model_selector_td_name'>";
        $output_lines[] = "<div class='model_selector_model_name'>" . $model_name . "</div>";
        $output_lines[] = "<div class='model_selector_model_mid'>" . $organization . "</div>";
        $output_lines[] = "</td>";
        $output_lines[] = "<td class='model_selector_td_score'>" . $score_text . "</td>";
        $output_lines[] = "<td class='model_selector_td_votes'>" . $votes_text . "</td>";
        $output_lines[] = "</tr>";

        return implode("\r\n", $output_lines);
    }
}

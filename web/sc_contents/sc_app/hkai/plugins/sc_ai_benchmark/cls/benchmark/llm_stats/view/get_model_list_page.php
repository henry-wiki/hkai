<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\llm_stats\view;

// == sc_ai_benchmark benchmark/llm_stats view get_model_list_page：view 動作（trait——掛入 view_Class；檔＝動作） ==
// == 2026-10-07 自 benchmark/aa 複製後改為 LLM Stats 顯示（欄：Model（model_name＋organization_name 兩行）／Score／Release Date；rows 已由 fetch 排序） ==
// == 細件（tbody／row）同檔——太簡單唔分檔 ==

trait get_model_list_page
{
    // 砌 bench_source 頁 HTML（大 table——bench_source 資訊列＋欄頭＋指定 entry 嘅 rows；後端砌，前端只 insert）
    public function get_model_list_page($bench_source_id, $entry_name)
    {
        // 本 entry 嘅 store 檔（合併規格列——搵返 fetch_category 對應嗰項）
        $navigation_items = bench_source()->get_bench_source_navigation_items();
        $entry_file = "";
        $score_format = "number";
        // 逐項搵（fetch_category 對上 = 本 entry）
        foreach ($navigation_items as $navigation_item) {
            if ($navigation_item["fetch_category"] === $entry_name) {
                $entry_file = $navigation_item["bench_source_file"];
                $score_format = $navigation_item["score_format"];
            }
        }

        // bench_source 檔數據（包內自取——repo 子域；一次載入，讀 llm_stats 段（rows）＋generated_at）
        $bench_source_data = bench_source()->repo()->get_bench_source_data($entry_file);
        $entry_rows = [];
        $generated_at = "";
        // 判定：bench_source 檔存在且合法 → 讀數據
        if ($bench_source_data !== null) {
            $entry_rows = $bench_source_data["llm_stats"];
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
            $output_lines[] = "<tr class='model_selector_empty_row'><td colspan='3'>No leaderboard data — click [Refresh] to download</td></tr>";
        }
        else
        {
            // 逐 row 砌一列（次序照 fetch 排序——排行榜次序）
            foreach ($entry_rows as $entry_row_item) {
                $output_lines[] = $this->build_llm_stats_row_html($entry_row_item, $score_format);
            }
        }

        $output_lines[] = "</tbody>";
        $output_lines[] = "</table>";
        $output_lines[] = "</div>";

        return implode("\r\n", $output_lines);
    }

    // 砌 bench_source 資訊 tbody（搜尋框＋下載時間＋Refresh＋欄頭列：Model／Score／Release Date）
    private function build_tbody_bench_source_html($bench_source_id, $generated_at)
    {
        // 下載時間（缺 → N/A）
        $bench_source_time_text = $generated_at ? $generated_at : "N/A";

        $output_lines = [];
        $output_lines[] = "<tbody data-tbody='bench_source' class='thead' data-bench_source_id='" . $bench_source_id . "'>";
        $output_lines[] = "<tr class='model_selector_bench_source_row' data-bench_source_id='" . $bench_source_id . "'>";
        $output_lines[] = "<td colspan='3'>";
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
        $output_lines[] = "<td>Model</td>";
        $output_lines[] = "<td class='model_selector_td_score'>Score</td>";
        $output_lines[] = "<td class='model_selector_td_votes'>Release Date</td>";
        $output_lines[] = "</tr>";
        $output_lines[] = "</tbody>";

        return implode("\r\n", $output_lines);
    }

    // 砌單一 row 列（model（兩行：名＋org）＋本 entry 分欄分數＋Release Date；純顯示——LLM Stats 榜）
    private function build_llm_stats_row_html($entry_row_item, $score_format)
    {
        // 從 array 取值一律先落同名變數（顯示層只放純變數）
        $model_name = $entry_row_item["model_name"];
        $organization_name = $entry_row_item["organization_name"];
        $score = $entry_row_item["score"];
        $release_date = $entry_row_item["release_date"];

        // 顯示文字（本 entry 分欄——按聲明 score_format 出；冇分 → 空）
        $score_text = "";
        // 判定：有分 → 按格式出
        if ($score !== null) {
            // 判定：percent（原始 0–1 分數制 → ×100 一位小數＋%）
            if ($score_format === "percent") {
                $score_text = number_format($score * 100, 1) . "%";
            }
            // 判定：number_trim（最多 3 位小數、尾零剪走——LLM Stats 混合尺度用）
            elseif ($score_format === "number_trim") {
                $score_text = rtrim(rtrim(sprintf("%.3f", $score), "0"), ".");
            }
            else
            {
                // 其餘（number——一位小數）
                $score_text = number_format($score, 1);
            }
        }

        $output_lines = [];
        $output_lines[] = "<tr class='model_selector_model_row'>";
        $output_lines[] = "<td class='model_selector_td_name'>";
        $output_lines[] = "<div class='model_selector_model_name'>" . $model_name . "</div>";
        $output_lines[] = "<div class='model_selector_model_mid'>" . $organization_name . "</div>";
        $output_lines[] = "</td>";
        $output_lines[] = "<td class='model_selector_td_score'>" . $score_text . "</td>";
        $output_lines[] = "<td class='model_selector_td_votes'>" . $release_date . "</td>";
        $output_lines[] = "</tr>";

        return implode("\r\n", $output_lines);
    }
}

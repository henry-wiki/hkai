<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\aa\fetch;

// == sc_ai_benchmark benchmark/aa fetch redownload_bench_source_models：域動作（trait——掛入 bench_source_aa_Class；檔＝動作） ==
// == 2026-10-07 自 benchmark/swe 複製後改為 Artificial Analysis 流程（逐 entry 抓取 → 逐 entry 組裝 → 寫新檔 → 交換上線；每 entry 一個 store 檔） ==

trait redownload_bench_source_models
{
    // redownload_bench_source_models 主流程（逐 entry 抓取 → 組裝 → 寫新檔 → 交換上線）——回傳結果 array（request_Class 聚合）
    // 結果契約：error = 0（成功，附 entry_count/backup_files/generated_at）／非 0（失敗，附 error_message）
    //   error 碼：1 = 抓取失敗；2 = API 回空清單；3 = 寫入新檔／交換上線失敗
    public function redownload_bench_source_models()
    {
        // 抓取全部 rows（失敗 → 回錯誤，不碰舊檔）
        $fetch_result = bench_source()->fetch_rows();
        if ($fetch_result["ok"] !== true) {
            return ["error" => 1, "error_message" => "failed to fetch aa rows: " . $fetch_result["error"]];
        }
        $entry_results = $fetch_result["entry_results"];
        if (count($entry_results) === 0) {
            return ["error" => 2, "error_message" => "aa returned empty row list"];
        }

        // 第一輪：逐 entry 組裝產檔內容並寫入各自新檔（寫入屬本職責——交換交共用 repo）
        $new_file_paths = [];
        // 逐 entry 組裝（失敗 → 止步——仲未動過任何正式檔）
        foreach ($entry_results as $entry_result) {
            $bench_source_file = $entry_result["bench_source_file"];
            $entry_row_list = $entry_result["rows"];

            $build_result = bench_source()->build_bench_source_file_content($bench_source_file, $entry_row_list);
            if ($build_result["ok"] !== true) {
                return ["error" => 3, "error_message" => $build_result["error"]];
            }
            $new_file_paths[$bench_source_file] = $build_result["new_file_path"];
        }

        // 第二輪：逐 entry 交換上線（共用工具——原檔 rename 為單層 .bak + 新檔 rename 上線）
        $backup_files = [];
        $total_row_count = 0;
        // 逐 entry 交換（失敗 → 回錯誤；先前已交換嘅 entry 維持上線）
        foreach ($entry_results as $entry_result) {
            $bench_source_file = $entry_result["bench_source_file"];

            $swap_result = sc_ai_benchmark()->repo()->swap_bench_source_file_with_single_backup($bench_source_file, $new_file_paths[$bench_source_file]);
            if ($swap_result["ok"] !== true) {
                return ["error" => 3, "error_message" => $swap_result["error"]];
            }
            $backup_files[] = $swap_result["backup_file"];
            $total_row_count += count($entry_result["rows"]);
        }

        // 成功回報（error = 0 + 行數/備份檔/generated_at——供前端 console 確認）
        $result_data                  = [];
        $result_data["error"]         = 0;
        $result_data["entry_count"]   = $total_row_count;
        $result_data["backup_files"]  = $backup_files;
        $result_data["generated_at"]  = date("Y-m-d H:i:s");

        return $result_data;
    }
}

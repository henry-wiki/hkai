<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\llm_stats\repo;

// == sc_ai_benchmark benchmark/llm_stats repo get_bench_source_data：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-07 自 benchmark/aa 複製後改為 LLM Stats（llm_stats 段查驗） ==
// == 2026-10-07 多 entry：檔路徑由呼叫端傳入（每 entry 獨立 store 檔） ==

trait get_bench_source_data
{
    // bench_source 檔資料（傳入檔路徑；file_exists 防禦 → require → 格式查驗；不合法 → null）
    public function get_bench_source_data($bench_source_file)
    {
        // bench_source 檔可能尚未產生（未跑下載前）——不存在 → null（require 不存在檔 = fatal）
        if (!file_exists($bench_source_file)) {
            return null;
        }

        $bench_source_data = require $bench_source_file;

        // bench_source 檔格式查驗（非陣列 → null）
        if (!is_array($bench_source_data)) {
            return null;
        }
        // 判定：無 llm_stats 段 → null（格式唔啱）
        if (!isset($bench_source_data["llm_stats"])) {
            return null;
        }

        return $bench_source_data;
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\arena\repo;

// == sc_ai_benchmark benchmark/arena repo get_bench_source_data：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-07 LM Arena 排行榜讀取（arena 段查驗；歷經兩次更名） ==
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
        // 判定：無 arena 段 → null（格式唔啱）
        if (!isset($bench_source_data["arena"])) {
            return null;
        }

        return $bench_source_data;
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\repo\repo_select;

// == sc_ai_benchmark repo get_model_item：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_benchmark_main 分遷入（one file one action） ==
// == 2026-10-06 自 main 域分遷入 repo（one file one action） ==

trait get_model_item
{
    // 從可用 bench_source 聲明 bench_source_file（絕對路徑）讀取指定 model 的完整 metadata（daemon_config 驗證 reasoning_effort 用）
    public function get_model_item($bench_source_id, $model_id)
    {
        // 可用 bench_source 聲明（bench_source_id → bench_source_file 絕對路徑）
        $available_bench_source = sc_ai_benchmark()->main()->get_available_bench_source();
        // 未 declared（唔喺可用聲明）→ 無此 bench_source
        if (!isset($available_bench_source[$bench_source_id])) {
            return null;
        }
        $bench_source_item = $available_bench_source[$bench_source_id];
        // 多 entry：各 entry 各有自己 store 檔——取第一 navigation item
        $bench_source_navigation = $bench_source_item["navigation"];
        $first_navigation_item = $bench_source_navigation[0];
        $bench_source_file = $first_navigation_item["bench_source_file"];
        if (!file_exists($bench_source_file)) {
            return null;
        }
        $data = require $bench_source_file;
        if (!is_array($data) || !isset($data["model"])) {
            return null;
        }
        // model item 存在於任一 tier（free / paid_with_coding_index / ...）
        $_model_item = null;
        foreach ($data["model"] as $tier_items) {
            if (isset($tier_items[$model_id])) {
                $_model_item = $tier_items[$model_id];
                break;
            }
        }
        
        if(@$_model_item)
        {
            $_reasoning_supported_efforts = (isset($_model_item["reasoning_supported_efforts"]) ? $_model_item["reasoning_supported_efforts"] : null);
            if($_reasoning_supported_efforts)
            {
                $_reasoning_supported_efforts = explode(",",$_reasoning_supported_efforts);
                
                $_model_item["reasoning_supported_efforts"] = $_reasoning_supported_efforts;
            } 
        }
        
        
        return $_model_item;
    }
}

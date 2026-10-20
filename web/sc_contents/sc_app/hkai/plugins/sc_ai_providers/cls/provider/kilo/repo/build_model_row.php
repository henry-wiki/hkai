<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\repo;

// == sc_ai_providers provider/kilo repo build_model_row：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-06 自 cls/repo/repo_select 遷入（provider 讀取——每 provider 獨立一包） ==

trait build_model_row
{
    // 單一 model row 組裝（provider 檔 item → modal 顯示欄位；tier 併入）
    // isset 防禦依據：coding_index / agentic_index 於 paid_without_coding_index tier 缺、reasoning_* 部分模型缺（已核對 provider 檔實況）
    private function build_model_row($tier_key, $model_id, $model_item)
    {
        // reasoning efforts（逗號字串 → 陣列——同 get_model_item 既有處理）
        $_reasoning_supported_efforts = [];
        if (isset($model_item["reasoning_supported_efforts"])) {
            $_reasoning_supported_efforts = explode(",", $model_item["reasoning_supported_efforts"]);
        }
        
        $_model_row = [];
        $_model_row["model_id"] = $model_id;
        $_model_row["name"] = $model_item["name"];
        $_model_row["tier"] = $tier_key;
        $_model_row["context_length"] = $model_item["context_length"];
        $_model_row["pricing_input"] = $model_item["pricing_input"];
        $_model_row["pricing_output"] = $model_item["pricing_output"];
        $_model_row["pricing_input_cache_read"] = isset($model_item["pricing_input_cache_read"]) ? $model_item["pricing_input_cache_read"] : "";
        $_model_row["coding_index"] = isset($model_item["coding_index"]) ? $model_item["coding_index"] : null;
        $_model_row["agentic_index"] = isset($model_item["agentic_index"]) ? $model_item["agentic_index"] : null;
        $_model_row["reasoning_supported_efforts"] = $_reasoning_supported_efforts;
        $_model_row["reasoning_default_effort"] = isset($model_item["reasoning_default_effort"]) ? $model_item["reasoning_default_effort"] : "";
        
        return $_model_row;
    }
}

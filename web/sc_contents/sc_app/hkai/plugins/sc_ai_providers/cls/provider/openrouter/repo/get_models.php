<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\repo;

// == sc_ai_providers provider/openrouter repo get_models：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-06 自 cls/repo/repo_select 遷入（provider 讀取——每 provider 獨立一包） ==

trait get_models
{
    // 本 provider 的 model 列表（讀 provider 檔——逐 tier 收集成 flat 列表，欄位裁剪至 modal 所需）
    public function get_models()
    {
        // 載入 provider 檔（不合法 → 空列表）
        $provider_data = provider()->repo()->get_provider_model_data();
        if ($provider_data === null) {
            return [];
        }

        // 逐 tier 逐 model 收集（tier 記入每列供顯示；以 model_id 為 key——前端 get_model_data_model 直接查表）
        $model_rows = [];
        foreach ($provider_data["model"] as $tier_key => $tier_items) {
            foreach ($tier_items as $model_id => $model_item) {
                $model_rows[$model_id] = provider()->repo()->build_model_row($tier_key, $model_id, $model_item);
            }
        }

        return $model_rows;
    }
}

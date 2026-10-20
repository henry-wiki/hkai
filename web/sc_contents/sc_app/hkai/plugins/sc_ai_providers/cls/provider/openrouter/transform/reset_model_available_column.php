<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\transform;

// == sc_ai_providers provider/openrouter transform reset_model_available_column：域動作（trait——掛入 provider_openrouter_Class；檔＝動作） ==
// == 2026-10-06 自 provider/openrouter/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait reset_model_available_column
{
    private function reset_model_available_column($model_list)
    {
        // 將白名單陣列的值轉為 Key (建立 Hash 表)，查詢時間複雜度降為 O(1)
        $allowed_keys = array_flip(provider()->model_available_column);
        
        foreach ($model_list as &$model_item) {
            // 直接取 key 的交集，只保留在白名單中的欄位
            $model_item = array_intersect_key($model_item, $allowed_keys);
        }
        unset($model_item);
        
        return $model_list;
    }
}

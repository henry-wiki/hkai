<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\tier;

// == sc_ai_providers provider/kilo tier split_provider_tiers：域動作（trait——掛入 provider_kilo_Class；檔＝動作） ==
// == 2026-10-06 自 provider/kilo/action/fetch_models.php 分遷入（按函數工作內容分檔） ==

trait split_provider_tiers
{
    // 過濾 + 分 tier（dirty 收集不產檔；free / paid_with_coding_index / paid_without_coding_index——回傳 buckets）
    private function split_provider_tiers($model_list)
    {
        $provider_paid_models_without_coding_index = [];
        $provider_paid_models_with_coding_index    = [];
        $provider_free_models                      = [];
        $provider_paid_models                      = [];
        $provider_dirty_models                     = [];
        
        foreach ($model_list as &$model_item) {
            // 無 id 的項目跳過（key 必需）
            $_model_id = isset($model_item["id"]) ? $model_item["id"] : "";
            if ($_model_id === "") {
                continue;
            }
            
            // created（epoch int → Y-m-d 字串——dirty 規則比對用）
            if (!empty($model_item["created"]))
            {
                $model_item["created"] = date("Y-m-d",$model_item["created"]);
            }
            
            // dirty 過濾（kilo 版規則——無 reasoning 資料，"not reasoning" 規則不適用）
            $dirty_reason = "";
            $_is_dirty    = provider()->is_dirty_model_item($model_item,$dirty_reason);
            if($_is_dirty)
            {
                $model_item["dirty_reason"] = $dirty_reason;
                $provider_dirty_models[$_model_id] = $model_item;
                continue;
            }
            
            // 分 tier（is_free → free；其餘依 coding_index 有無）
            if (!empty($model_item["is_free"]))
            {
                $provider_free_models[$_model_id] = $model_item;
            }
            else 
            {
                if (!empty($model_item["coding_index"]))
                {
                    $provider_paid_models_with_coding_index[$_model_id] = $model_item;
                }
                else 
                {
                    $provider_paid_models_without_coding_index[$_model_id] = $model_item;
                }
                
                $provider_paid_models[$_model_id] = $model_item;
            }
            
        }
        unset($model_item);

        // buckets 回傳（key 同名——呼叫端逐層落同名變數）
        return [
            "provider_free_models"                      => $provider_free_models,
            "provider_paid_models_with_coding_index"    => $provider_paid_models_with_coding_index,
            "provider_paid_models_without_coding_index" => $provider_paid_models_without_coding_index,
            "provider_paid_models"                      => $provider_paid_models,
            "provider_dirty_models"                     => $provider_dirty_models,
        ];
    }
}

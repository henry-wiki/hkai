<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\transform;

// == sc_ai_providers provider/openrouter transform reset_model_pricing：域動作（trait——掛入 provider_openrouter_Class；檔＝動作） ==
// == 2026-10-06 自 provider/openrouter/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait reset_model_pricing
{
    private function reset_model_pricing($model_list)
    {
        foreach($model_list as &$model_item)
        {
            $_model_id = $model_item["id"];
            
            foreach($model_item["pricing"] as &$price_item)
            {
                if(is_string($price_item))
                {
                    $price_item = bcmul($price_item,"1000000",8);
                    $price_item = rtrim(rtrim($price_item,'0'),'.');
                }
                else 
                {
                    // == this is the detail price, ignore
                    $price_item = null;
                }
            }unset($price_item);
            $model_item["pricing"] = array_filter($model_item["pricing"],function($item)    {return $item !== null;});
            
            
            if(isset($model_item["pricing"]["prompt"]))
            {
                $model_item["pricing_input"] = $model_item["pricing"]["prompt"];
            }
            if(isset($model_item["pricing"]["completion"]))
            {
                $model_item["pricing_output"] = $model_item["pricing"]["completion"];
            }
            // 快取命中輸入價（input_cache_read——顯示用：Model List 價欄第二行）
            if(isset($model_item["pricing"]["input_cache_read"]))
            {
                $model_item["pricing_input_cache_read"] = $model_item["pricing"]["input_cache_read"];
            }
            
            if(str_ends_with($_model_id,":free"))
            {
                $model_item["is_free"] = 1;
            }
            else 
            {
                $model_item["is_free"] = 0;
            }
            
        }
        unset($model_item);
        
        return $model_list;
    }
}

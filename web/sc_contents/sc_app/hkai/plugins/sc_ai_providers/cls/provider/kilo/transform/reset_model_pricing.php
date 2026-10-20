<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\transform;

// == sc_ai_providers provider/kilo transform reset_model_pricing：域動作（trait——掛入 provider_kilo_Class；檔＝動作） ==
// == 2026-10-06 自 provider/kilo/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait reset_model_pricing
{
    // pricing 轉換（per-token 小数字串 ×1e6 同 openrouter 基準；isFree → is_free；"-1" = unknown → 移除）
    private function reset_model_pricing($model_list)
    {
        foreach($model_list as &$model_item)
        {
            $_pricing = isset($model_item["pricing"]) ? $model_item["pricing"] : [];
            
            foreach($_pricing as &$price_item)
            {
                if(is_string($price_item))
                {
                    // "-1" = unknown（kilo-auto 等路由模型）→ 移除（dirty 規則 no price 接手）
                    if($price_item === "-1")
                    {
                        $price_item = null;
                        continue;
                    }
                    
                    $price_item = bcmul($price_item,"1000000",8);
                    $price_item = rtrim(rtrim($price_item,'0'),'.');
                }
                else 
                {
                    // == this is the detail price, ignore
                    $price_item = null;
                }
            }unset($price_item);
            $_pricing = array_filter($_pricing,function($item)    {return $item !== null;});
            
            $model_item["pricing"] = $_pricing;
            
            if(isset($_pricing["prompt"]))
            {
                $model_item["pricing_input"] = $_pricing["prompt"];
            }
            if(isset($_pricing["completion"]))
            {
                $model_item["pricing_output"] = $_pricing["completion"];
            }
            // 快取命中輸入價（input_cache_read——顯示用：Model List 價欄第二行）
            if(isset($_pricing["input_cache_read"]))
            {
                $model_item["pricing_input_cache_read"] = $_pricing["input_cache_read"];
            }
            
            // isFree（bool）→ is_free（1/0）
            if (!empty($model_item["isFree"]))
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

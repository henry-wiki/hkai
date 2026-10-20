<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\transform;

// == sc_ai_providers provider/openrouter transform reset_model_benchmark：域動作（trait——掛入 provider_openrouter_Class；檔＝動作） ==
// == 2026-10-06 自 provider/openrouter/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait reset_model_benchmark
{
    private function reset_model_benchmark($model_list)
    {
        foreach($model_list as &$model_item)
        {
            $_model_id = $model_item["id"];
            
            $_reasoning = (isset($model_item["reasoning"]) ? $model_item["reasoning"] : null);
            $_mandatory = (isset($_reasoning["mandatory"]) ? $_reasoning["mandatory"] : null);
            
            $artificial_analysis = (isset($model_item["benchmarks"]["artificial_analysis"]) ? $model_item["benchmarks"]["artificial_analysis"] : null);
            
            if(!isset($artificial_analysis))
            {
                continue;
            }
            
            
            if (!empty($artificial_analysis["intelligence_index"]))
            {
                $model_item["intelligence_index"] = floatval($artificial_analysis["intelligence_index"]);
            }
            if (!empty($artificial_analysis["coding_index"]))
            {
                $model_item["coding_index"] = floatval($artificial_analysis["coding_index"]);
            }
            if (!empty($artificial_analysis["agentic_index"]))
            {
                $model_item["agentic_index"] = floatval($artificial_analysis["agentic_index"]);
            }
            
            
            
        }
        unset($model_item);
        
        return $model_list;
    }
}

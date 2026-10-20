<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\transform;

// == sc_ai_providers provider/openrouter transform reset_model_reasoning：域動作（trait——掛入 provider_openrouter_Class；檔＝動作） ==
// == 2026-10-06 自 provider/openrouter/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait reset_model_reasoning
{
    private function reset_model_reasoning($model_list)
    {
        foreach($model_list as &$model_item)
        {
            $_model_id = $model_item["id"];
            
            $_reasoning = (isset($model_item["reasoning"]) ? $model_item["reasoning"] : null);
            $_mandatory = (isset($_reasoning["mandatory"]) ? $_reasoning["mandatory"] : null);
            
            if(!isset($_mandatory))
            {
                continue;
            }
            
            $model_item["reasoning_mandatory"] = $_mandatory;
            
            if(isset($_reasoning["default_enabled"]))
            {
                $model_item["reasoning_default_enabled"] = (isset($_reasoning["default_enabled"]) ? $_reasoning["default_enabled"] : null);
            }
            if(isset($_reasoning["supported_efforts"]))
            {
                $model_item["reasoning_supported_efforts"] = @implode(",",$_reasoning["supported_efforts"]);
            }
            
            if(isset($_reasoning["default_effort"]))
            {
                $model_item["reasoning_default_effort"] = (isset($_reasoning["default_effort"]) ? $_reasoning["default_effort"] : null);
            }
            
        }
        unset($model_item);
        
        return $model_list;
    }
}

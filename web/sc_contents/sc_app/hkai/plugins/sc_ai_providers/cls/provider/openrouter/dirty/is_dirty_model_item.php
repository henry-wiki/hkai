<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\dirty;

// == sc_ai_providers provider/openrouter dirty is_dirty_model_item：域動作（trait——掛入 provider_openrouter_Class；檔＝動作） ==
// == 2026-10-06 自 provider/openrouter/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait is_dirty_model_item
{
    private function is_dirty_model_item($model_item,&$dirty_reason = "")
    {
        $_is_dirty = false;
        
        // free 模型跳過所有 dirty 檢查
        if ($model_item["is_free"]) {
            return false;
        }
        
        $_model_id = $model_item["id"];
        $_pricing  = isset($model_item["pricing"]) ? $model_item["pricing"] : [];
        
        $_pricing_input  = (isset($model_item["pricing_input"]) ? $model_item["pricing_input"] : null);
        $_pricing_output = (isset($model_item["pricing_output"]) ? $model_item["pricing_output"] : null);
        
        $_reasoning = isset($model_item["reasoning"]);
        
        
        if(!$_pricing)
        {
            $_is_dirty    = true;
            $dirty_reason = "no price #1";
            
            return $_is_dirty;
        }
        
        if(!isset($_pricing_input) || !isset($_pricing_output))
        {
            $_is_dirty    = true;
            $dirty_reason = "no price #2";
            
            return $_is_dirty;
        }
        
        if(@floatval($_pricing_input) > 10)
        {
            $_is_dirty    = true;
            $dirty_reason = "too expensive";
            
            return $_is_dirty;
        }
        
        if($_pricing_input > 0)
        {
            if(!$_reasoning)
            {
                $_is_dirty    = true;
                $dirty_reason = "not reasoning";
                
                return $_is_dirty;
            }
        }
        
        
        // 檢查 model_id 是否含任何 dirty 標籤
        foreach (provider()->dirty_model_id_tags as $dirty_tag) {
            if (strpos($_model_id, $dirty_tag) !== false) {
                $_is_dirty    = true;
                $dirty_reason = "match dirty tag : {$dirty_tag}";
                break;
            }
        }
        
        $context_length = (isset($model_item["context_length"]) ? $model_item["context_length"] : null);
        if($context_length < (256 * 1000))
        {
            $dirty_reason = "context_length < 256k";
            $_is_dirty    = true;
            
            return $_is_dirty;
        }
        
        // 音樂生成模型（非文字對話）
        $output_modalities = (isset($model_item["architecture"]["output_modalities"]) ? $model_item["architecture"]["output_modalities"] : null);
        if(is_array($output_modalities))
        {
            if(in_array("audio",$output_modalities))
            {
                $dirty_reason = "for audio";
                $_is_dirty    = true;
                
                return $_is_dirty;
            }
        }
        
        if(isset($model_item["coding_index"]))
        {
            $coding_index = (isset($model_item["coding_index"]) ? $model_item["coding_index"] : null);
            if($coding_index < 45)
            {
                $dirty_reason = "coding_index < 45";
                $_is_dirty    = true;
                
                return $_is_dirty;
            }
            
            if($coding_index < 50 && floatval($_pricing_input) > 1)
            {
                $dirty_reason = "coding_index < 50, and input price > 1";
                $_is_dirty    = true;
                
                return $_is_dirty;
            }
            
        }
        else 
        {
            // no coding index 
            if(floatval($_pricing_input) > 0.5)
            {
                $dirty_reason = "no coding_index and input price > 0.5";
                $_is_dirty    = true;
                
                return $_is_dirty;
            }
            
            
        }
        
        if(isset($model_item["agentic_index"]))
        {
            $agentic_index = (isset($model_item["agentic_index"]) ? $model_item["agentic_index"] : null);
            if($agentic_index < 25)
            {
                $dirty_reason = "agentic_index < 25";
                $_is_dirty    = true;
                
                return $_is_dirty;
            }
            
            if($agentic_index < 30 && floatval($_pricing_input) > 1)
            {
                $dirty_reason = "$agentic_index < 30, and input price > 1";
                $_is_dirty    = true;
                
                return $_is_dirty;
            }
        }
        
        
        
        
        
        if($model_item["created"])
        {
            if($model_item["created"] < "2024-01-01")
            {
                $dirty_reason = "created date < 2024-01-01";
                $_is_dirty    = true;
                
                return $_is_dirty;
            }
        }
        
        
        return $_is_dirty;
    }
}

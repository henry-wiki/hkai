<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\transform;

// == sc_ai_providers provider/kilo transform reset_model_coding_index：域動作（trait——掛入 provider_kilo_Class；檔＝動作） ==
// == 2026-10-06 自 provider/kilo/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait reset_model_coding_index
{
    // coding_index 映射（terminalBench.overallScore 0-1 → ×100 百分制；33/371 models 有值）
    private function reset_model_coding_index($model_list)
    {
        foreach($model_list as &$model_item)
        {
            $terminal_bench = (isset($model_item["terminalBench"]) ? $model_item["terminalBench"] : null);
            
            if(!isset($terminal_bench))
            {
                continue;
            }
            
            $overall_score = (isset($terminal_bench["overallScore"]) ? $terminal_bench["overallScore"] : null);
            if(isset($overall_score))
            {
                $model_item["coding_index"] = round(floatval($overall_score) * 100, 2);
            }
            
        }
        unset($model_item);
        
        return $model_list;
    }
}

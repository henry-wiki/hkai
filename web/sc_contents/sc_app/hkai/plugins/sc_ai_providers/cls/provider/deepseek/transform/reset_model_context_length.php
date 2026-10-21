<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\deepseek\transform;

// == sc_ai_providers provider/deepseek transform reset_model_context_length：域動作（trait——掛入 provider_deepseek_Class；檔＝動作） ==

trait reset_model_context_length
{
    // context 欄位映射（DeepSeek：context_window → context_length——同白名單欄名對齊）
    private function reset_model_context_length($model_list)
    {
        foreach ($model_list as &$model_item) {
            // 判定：有 context_window → 映射去 context_length
            if (isset($model_item["context_window"])) {
                $model_item["context_length"] = $model_item["context_window"];
            }
        }
        unset($model_item);

        return $model_list;
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\deepseek\transform;

// == sc_ai_providers provider/deepseek transform reset_model_reasoning：域動作（trait——掛入 provider_deepseek_Class；檔＝動作） ==
// == DeepSeek effort 結構 → 本專案標準 reasoning_* 欄位 ==

trait reset_model_reasoning
{
    // reasoning 欄位映射（effort.supported_levels → reasoning_supported_efforts；effort.default_level → reasoning_default_effort）
    private function reset_model_reasoning($model_list)
    {
        foreach ($model_list as &$model_item) {
            $model_effort = isset($model_item["effort"]) ? $model_item["effort"] : null;

            // 判定：冇 effort 資料 → 跳過
            if (!is_array($model_effort)) {
                continue;
            }

            // 判定：有 supported_levels → 串成逗號字串（標準欄位格式同 openrouter 一致）
            if (isset($model_effort["supported_levels"]) && is_array($model_effort["supported_levels"])) {
                $model_item["reasoning_supported_efforts"] = implode(",", $model_effort["supported_levels"]);
            }

            // 判定：有 default_level → 落 reasoning_default_effort
            if (isset($model_effort["default_level"])) {
                $model_item["reasoning_default_effort"] = $model_effort["default_level"];
            }
        }
        unset($model_item);

        return $model_list;
    }
}

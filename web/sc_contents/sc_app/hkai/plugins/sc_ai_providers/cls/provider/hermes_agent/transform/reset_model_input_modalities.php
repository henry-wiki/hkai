<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\hermes_agent\transform;

// == sc_ai_providers provider/hermes_agent transform reset_model_input_modalities：域動作（trait——掛入 provider_hermes_agent_Class；檔＝動作） ==
// == 職責：architecture.input_modalities → 逗號字串（顯示用；例如 text, image） ==

trait reset_model_input_modalities
{
    private function reset_model_input_modalities($model_list)
    {
        foreach ($model_list as &$model_item) {
            $architecture  = $model_item["architecture"] ?? [];
            $modality_list = $architecture["input_modalities"] ?? [];

            // 判定：模態清單係陣列 → 轉逗號字串
            if (is_array($modality_list)) {
                $model_item["input_modalities"] = implode(", ", $modality_list);
            }
        }
        unset($model_item);

        return $model_list;
    }
}

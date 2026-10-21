<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\deepseek\repo;

// == sc_ai_providers provider/deepseek repo get_provider_model_data：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-06 自 cls/repo/repo_select 遷入（provider 讀取——每 provider 獨立一包） ==

trait get_provider_model_data
{
    // provider 檔 model 資料（file_exists 防禦 → require → 格式查驗；不合法 → null）
    public function get_provider_model_data()
    {
        $provider_file = provider()->get_provider_file_path();

        // provider 檔可能尚未產生（未跑 reset 前）——不存在 → null（require 不存在檔 = fatal）
        if (!file_exists($provider_file)) {
            return null;
        }

        $provider_data = require $provider_file;

        // provider 檔格式查驗（非陣列或無 model tier 結構 → null）
        if (!is_array($provider_data) || !isset($provider_data["model"])) {
            return null;
        }

        return $provider_data;
    }
}

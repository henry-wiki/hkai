<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\repo;

// == sc_ai_providers provider/kilo repo get_provider_generated_at：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-06 自 cls/repo/repo_select 遷入（provider 讀取——每 provider 獨立一包） ==

trait get_provider_generated_at
{
    // provider 檔 generated_at（last update time——model selector provider 節行顯示用；檔不存在 → null）
    public function get_provider_generated_at()
    {
        // 載入 provider 檔（不合法 → null）
        $provider_data = provider()->repo()->get_provider_model_data();
        if ($provider_data === null) {
            return null;
        }

        return isset($provider_data["generated_at"]) ? $provider_data["generated_at"] : null;
    }
}

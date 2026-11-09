<?php

namespace sc\app\hkai\plugins\sc_ai_providers\repo\repo_select;

// == sc_ai_providers repo get_model_item：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_providers_main 分遷入（one file one action） ==
// == 2026-10-06 自 main 域分遷入 repo（one file one action） ==

trait get_model_item
{
    // 從可用 provider 聲明 provider_file（絕對路徑）讀取指定 model 的完整 metadata（daemon_config 驗證 reasoning_effort 用）
    public function get_model_item($provider_id, $model_id)
    {
        // provider 例項（解析入口——同 get_models 同一條來源）
        $provider_instance = sc_ai_providers()->repo()->get_provider_instance($provider_id);

        // 判定：provider 未實作／未宣告 → 無此 provider
        if ($provider_instance === null) {
            return null;
        }

        // provider 檔資料（走 provider repo 嘅解析——store → data override fallback；同 get_models 一致）
        $data = $provider_instance->repo()->get_provider_model_data();

        // 判定：provider 檔未產生／格式不合法 → null
        if ($data === null) {
            return null;
        }
        // model item 存在於任一 tier（free / paid_with_coding_index / ...）
        $_model_item = null;
        foreach ($data["model"] as $tier_items) {
            if (isset($tier_items[$model_id])) {
                $_model_item = $tier_items[$model_id];
                break;
            }
        }
        
        if(@$_model_item)
        {
            $_reasoning_supported_efforts = (isset($_model_item["reasoning_supported_efforts"]) ? $_model_item["reasoning_supported_efforts"] : null);
            if($_reasoning_supported_efforts)
            {
                $_reasoning_supported_efforts = explode(",",$_reasoning_supported_efforts);
                
                $_model_item["reasoning_supported_efforts"] = $_reasoning_supported_efforts;
            } 
        }
        
        
        return $_model_item;
    }
}

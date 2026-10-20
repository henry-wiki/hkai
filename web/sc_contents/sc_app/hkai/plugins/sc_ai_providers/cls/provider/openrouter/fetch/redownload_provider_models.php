<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\fetch;

// == sc_ai_providers provider/openrouter fetch redownload_provider_models：域動作（trait——掛入 provider_openrouter_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_providers_reset_from_openrouter 分遷入（one file one action） ==
// == 2026-10-06 自 cls/reset_openrouter 遷入 cls/provider/openrouter（每 provider 獨立一包） ==
// == 2026-10-06 自 cls/provider/openrouter/action 遷入 cls/provider/openrouter/fetch ==

trait redownload_provider_models
{
    // redownload_provider_models 主流程（curl 抓取 → 轉換 → 排序 → 組裝 → 寫檔 → 驗證）——回傳結果 array（request_Class 聚合）
    // 結果契約：error = 0（成功，附 model_count/free_count/backup_file/generated_at）／非 0（失敗，附 error_message）
    //   error 碼：1 = 抓取失敗；2 = API 回空清單；3 = 管線異常／資料閘未過／寫入新檔／交換上線失敗
    public function redownload_provider_models()
    {
        // 抓取 models（失敗/空清單 → 回錯誤，不碰舊檔）
        $model_list = provider()->fetch_models_from_api();
        if ($model_list === null) {
            return ["error" => 1, "error_message" => "failed to fetch openrouter models"];
        }
        if (count($model_list) === 0) {
            return ["error" => 2, "error_message" => "openrouter returned empty model list"];
        }
        
        
        
        
        
        
        
        // 管線異常閘（開窗）：轉換～排序窗內任何 notice／warning 一律收集（@ 抑制除外）
        $pipeline_errors = [];

        set_error_handler(
            function ($error_level, $error_message, $error_file, $error_line) use (&$pipeline_errors) {
                // 尊重 @ 抑制（有意為之嘅靜默唔當錯——交返預設處理）
                if (!(error_reporting() & $error_level)) {
                    return false;
                }
                $pipeline_errors[] = $error_level . ": " . $error_message . "（" . $error_file . ":" . $error_line . "）";
                return true;
            }
        );

        // 轉換（每 model 依白名單複製欄位 + 衍生 is_free）——id 為 key、剔除無 id 項
        $model_list = provider()->reset_model_pricing($model_list);
        $model_list = provider()->reset_model_reasoning($model_list);
        $model_list = provider()->reset_model_benchmark($model_list);

        // 過濾 + 分 tier（dirty 收集不產檔；free / paid_with_coding_index / paid_without_coding_index）
        $provider_tiers = provider()->split_provider_tiers($model_list);

        $provider_free_models                      = $provider_tiers["provider_free_models"];
        $provider_paid_models_with_coding_index    = $provider_tiers["provider_paid_models_with_coding_index"];
        $provider_paid_models_without_coding_index = $provider_tiers["provider_paid_models_without_coding_index"];
        $provider_dirty_models                     = $provider_tiers["provider_dirty_models"];
        
        // -- reset model pricing 
        //$provider_paid_models_with_coding_index    = $this->reset_model_pricing($provider_paid_models_with_coding_index);
        //$provider_paid_models_without_coding_index = $this->reset_model_pricing($provider_paid_models_without_coding_index);
        
        
        $provider_free_models                      = provider()->reset_model_available_column($provider_free_models);
        $provider_paid_models_with_coding_index    = provider()->reset_model_available_column($provider_paid_models_with_coding_index);
        $provider_paid_models_without_coding_index = provider()->reset_model_available_column($provider_paid_models_without_coding_index);
        
        
        // 兩表各自依多欄規格排序（分流表內同 is_free——規格不需含 is_free）
        provider()->sort_provider_models($provider_free_models);
        provider()->sort_provider_models($provider_paid_models_with_coding_index);
        provider()->sort_provider_models($provider_paid_models_without_coding_index);
        
        $provider_paid_models = array_replace($provider_paid_models_with_coding_index,$provider_paid_models_without_coding_index);
        
        // 管線異常閘（收窗）：窗內收集到嘅任何 notice／warning 都唔准上線（舊檔原封）
        restore_error_handler();

        // 判定：有收集 → 回錯（附原文——前端 console 可見）
        if (count($pipeline_errors) > 0) {
            return ["error" => 3, "error_message" => "pipeline errors: " . implode(" | ", $pipeline_errors)];
        }

        // 資料閘①：五桶必須全係 array（靜默 null 流唔可以上線）
        $tier_buckets = [
            "free" => $provider_free_models,
            "paid_with_coding_index" => $provider_paid_models_with_coding_index,
            "paid_without_coding_index" => $provider_paid_models_without_coding_index,
            "paid" => $provider_paid_models,
            "dirty" => $provider_dirty_models,
        ];

        // 逐桶查驗——有非 array 即回錯（舊檔原封）
        foreach ($tier_buckets as $tier_bucket_key => $tier_bucket_item) {
            // 判定：非 array → 回錯
            if (!is_array($tier_bucket_item)) {
                return ["error" => 3, "error_message" => "broken tier: " . $tier_bucket_key];
            }
        }

        // 資料閘②：自由＋付費＋dirty 總和必須 > 0（來源非空但全漏光＝管線斷）
        $provider_bucket_total = count($provider_free_models) + count($provider_paid_models) + count($provider_dirty_models);

        // 判定：全空 → 回錯（舊檔原封）
        if ($provider_bucket_total === 0) {
            return ["error" => 3, "error_message" => "broken tiers: all buckets empty"];
        }

        // 合併：免費在前、付費在後（array_replace——key 不重疊）
        $provider_models         = [];
        $provider_models["free"] = $provider_free_models;
        //$provider_models["paid"]                      = $provider_paid_models;
        $provider_models["paid_with_coding_index"]    = $provider_paid_models_with_coding_index;
        $provider_models["paid_without_coding_index"] = $provider_paid_models_without_coding_index;
        //$provider_models["provider_dirty_models"]     = $provider_dirty_models;
        
        
        // 組裝產檔內容並寫入新檔（寫入屬本職責——交換交共用 repo）
        $build_result = provider()->build_provider_file_content($provider_models,$provider_dirty_models);
        if ($build_result["ok"] !== true) {
            return ["error" => 3, "error_message" => $build_result["error"]];
        }
        
        // 交換上線（共用工具——原檔 rename 為單層 .bak + 新檔 rename 上線）
        $write_result = sc_ai_providers()->repo()->swap_provider_file_with_single_backup(provider()->get_provider_file_path(), $build_result["new_file_path"]);
        if ($write_result["ok"] !== true) {
            return ["error" => 3, "error_message" => $write_result["error"]];
        }
        
        // 成功回報（error = 0 + 數量/備份檔/generated_at——供前端 console 確認）
        $result_data                 = [];
        $result_data["error"]        = 0;
        $result_data["model_count"]  = count($provider_free_models) + count($provider_paid_models);
        $result_data["free_count"]   = count($provider_free_models);
        $result_data["backup_file"]  = $write_result["backup_file"];
        $result_data["generated_at"] = date("Y-m-d H:i:s");
        
        return $result_data;
    }
}

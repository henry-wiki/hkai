<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\gemini\fetch;

// == sc_ai_providers provider/gemini fetch fetch_models_from_api：域動作（trait——掛入 provider_gemini_Class；檔＝動作） ==

trait fetch_models_from_api
{
    // 抓取 Gemini models 清單（sc_curl 標準：自動 UA／憑證驗證；認證走 x-goog-api-key）
    // 形狀差異：Gemini 回 {"models":[{name:"models/gemini-…", displayName, inputTokenLimit}]}——此處轉標準 item
    private function fetch_models_from_api()
    {
        // 本 provider 鎖匙（Settings › Provider 存入 sc_api_keys）
        $api_key = provider()->get_provider_api_key();

        // 判定：未有鎖匙 → 回錯誤（API 要 x-goog-api-key 驗證）
        if ($api_key === "") {
            return ["error" => "missing api key (" . provider()->get_provider_api_key_env() . ")"];
        }

        // 標準 HTTP 請求（逾時 30 秒——Gemini 專用 header）
        $curl_result = sc_curl(provider()->get_provider_models_api_url(), [
            "timeout" => 30,
            "headers" => ["x-goog-api-key: " . $api_key],
        ]);

        // 判定：請求失敗（傳輸錯誤或 HTTP >= 400）→ 回錯誤原文（地區封鎖／驗證失敗都靠佢顯示）
        if ($curl_result["ok"] !== true) {
            return ["error" => "models api failed: http " . $curl_result["status"]];
        }

        // 解析結果（sc_curl 已解 JSON——非 JSON 即 null）
        $api_data = $curl_result["json"];

        // 判定：無 models 鍵 → null
        if (!isset($api_data["models"])) {
            return null;
        }

        // 判定：models 非陣列 → null
        if (!is_array($api_data["models"])) {
            return null;
        }

        $model_list = [];

        // 逐個 model 轉標準 item（name 去 "models/" 前綴；只留支援 generateContent 嘅模型）
        foreach ($api_data["models"] as $gemini_model_item) {
            $model_methods = isset($gemini_model_item["supportedGenerationMethods"]) ? $gemini_model_item["supportedGenerationMethods"] : [];

            // 判定：方法清單非陣列 → 跳過
            if (!is_array($model_methods)) {
                continue;
            }

            // 判定：唔支援 generateContent → 跳過（embedding 等非對話模型）
            if (!in_array("generateContent", $model_methods)) {
                continue;
            }

            $gemini_model_name = $gemini_model_item["name"];
            $model_id          = str_replace("models/", "", $gemini_model_name);

            $model_row         = [];
            $model_row["id"]   = $model_id;
            $model_row["name"] = $model_id;

            // 判定：有 inputTokenLimit → 當 context 長度（顯示用）
            if (isset($gemini_model_item["inputTokenLimit"])) {
                $model_row["context_length"] = $gemini_model_item["inputTokenLimit"];
            }

            $model_list[] = $model_row;
        }

        return $model_list;
    }
}

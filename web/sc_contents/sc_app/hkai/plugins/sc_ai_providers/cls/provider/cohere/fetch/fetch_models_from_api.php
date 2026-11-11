<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\cohere\fetch;

// == sc_ai_providers provider/cohere fetch fetch_models_from_api：域動作（trait——掛入 provider_cohere_Class；檔＝動作） ==

trait fetch_models_from_api
{
    // 抓取 Cohere models 清單（sc_curl 標準：自動 UA／憑證驗證；需 Bearer 鎖匙）
    // 形狀差異：Cohere 回 {"models":[{name, endpoints, context_length}]}——此處轉成本專案標準 item
    private function fetch_models_from_api()
    {
        // 本 provider 鎖匙（Settings › Provider 存入 sc_api_keys）
        $api_key = provider()->get_provider_api_key();

        // 判定：未有鎖匙 → 回錯誤（API 要 Bearer 驗證）
        if ($api_key === "") {
            return ["error" => "missing api key (" . provider()->get_provider_api_key_env() . ")"];
        }

        // 標準 HTTP 請求（逾時 30 秒——Bearer 驗證）
        $curl_result = sc_curl(provider()->get_provider_models_api_url(), [
            "timeout" => 30,
            "headers" => ["Authorization: Bearer " . $api_key],
        ]);

        // 判定：請求失敗（傳輸錯誤或 HTTP >= 400）→ null（呼叫端回錯誤）
        if ($curl_result["ok"] !== true) {
            return null;
        }

        // 解析結果（sc_curl 已解 JSON——非 JSON 即 null）
        $api_data = $curl_result["json"];

        // 判定：無 models 鍵（Cohere 用 models 而非 data）→ null
        if (!isset($api_data["models"])) {
            return null;
        }

        // 判定：models 非陣列 → null
        if (!is_array($api_data["models"])) {
            return null;
        }

        $model_list = [];

        // 逐個 model 轉標準 item（name → id／name；context_length 沿用；只留支援 chat 嘅模型）
        foreach ($api_data["models"] as $cohere_model_item) {
            $model_endpoints = isset($cohere_model_item["endpoints"]) ? $cohere_model_item["endpoints"] : [];

            // 判定：唔支援 chat 端點 → 跳過（embed／rerank／classify 等非對話模型）
            if (!is_array($model_endpoints)) {
                continue;
            }

            // 判定：endpoints 無 chat → 跳過
            if (!in_array("chat", $model_endpoints)) {
                continue;
            }

            $cohere_model_name = $cohere_model_item["name"];

            $model_row         = [];
            $model_row["id"]   = $cohere_model_name;
            $model_row["name"] = $cohere_model_name;

            // 判定：有 context_length → 帶上（顯示用）
            if (isset($cohere_model_item["context_length"])) {
                $model_row["context_length"] = $cohere_model_item["context_length"];
            }

            $model_list[] = $model_row;
        }

        return $model_list;
    }
}

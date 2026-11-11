<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\anthropic\fetch;

// == sc_ai_providers provider/anthropic fetch fetch_models_from_api：域動作（trait——掛入 provider_anthropic_Class；檔＝動作） ==

trait fetch_models_from_api
{
    // 抓取 Anthropic models 清單（sc_curl 標準：自動 UA／憑證驗證；認證走 x-api-key ＋ anthropic-version）
    private function fetch_models_from_api()
    {
        // 本 provider 鎖匙（Settings › Provider 存入 sc_api_keys）
        $api_key = provider()->get_provider_api_key();

        // 判定：未有鎖匙 → 回錯誤（API 要 x-api-key 驗證）
        if ($api_key === "") {
            return ["error" => "missing api key (" . provider()->get_provider_api_key_env() . ")"];
        }

        // 標準 HTTP 請求（逾時 30 秒——Anthropic 專用 header）
        $curl_result = sc_curl(provider()->get_provider_models_api_url(), [
            "timeout" => 30,
            "headers" => [
                "x-api-key: " . $api_key,
                "anthropic-version: 2023-06-01",
            ],
        ]);

        // 判定：請求失敗（傳輸錯誤或 HTTP >= 400）→ 回錯誤原文（地區封鎖／驗證失敗都靠佢顯示）
        if ($curl_result["ok"] !== true) {
            return ["error" => "models api failed: http " . $curl_result["status"]];
        }

        // 解析結果（sc_curl 已解 JSON——非 JSON 即 null）
        $api_data = $curl_result["json"];

        // 判定：無 data 鍵 → null
        if (!isset($api_data["data"])) {
            return null;
        }

        // 判定：data 非陣列 → null
        if (!is_array($api_data["data"])) {
            return null;
        }

        return $api_data["data"];
    }
}

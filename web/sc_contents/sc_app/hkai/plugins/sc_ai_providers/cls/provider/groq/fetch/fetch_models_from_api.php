<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\groq\fetch;

// == sc_ai_providers provider/groq fetch fetch_models_from_api：域動作（trait——掛入 provider_groq_Class；檔＝動作） ==
// == 2026-10-06 自 provider/groq/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait fetch_models_from_api
{
    // 抓取 Groq models 清單（需 Bearer 鎖匙——sc_curl 標準：自動 UA／憑證驗證）
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

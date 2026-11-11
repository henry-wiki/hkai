<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\hermes_agent\fetch;

// == sc_ai_providers provider/hermes_agent fetch fetch_models_from_api：域動作（trait——掛入 provider_hermes_agent_Class；檔＝動作） ==
// == 2026-10-06 自 provider/hermes_agent/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait fetch_models_from_api
{
    // 抓取 Hermes Agent models 清單（公開 API——免 key；php.ini 已配 cainfo——verifypeer 正常開）
    // 抓取 Hermes Agent models 清單（sc_curl 標準——自動 UA／憑證驗證；公開 API 免 key）
    private function fetch_models_from_api()
    {
        // 標準 HTTP 請求（逾時 30 秒——同源共用設定）
        $curl_result = sc_curl(provider()->get_provider_models_api_url(), [
            "timeout" => 30,
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

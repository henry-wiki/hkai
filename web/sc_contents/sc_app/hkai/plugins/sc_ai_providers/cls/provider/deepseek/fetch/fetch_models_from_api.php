<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\deepseek\fetch;

// == sc_ai_providers provider/deepseek fetch fetch_models_from_api：域動作（trait——掛入 provider_deepseek_Class；檔＝動作） ==
// == 2026-10-06 自 provider/deepseek/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait fetch_models_from_api
{
    // 抓取 DeepSeek models 清單（公開 API——免 key；php.ini 已配 cainfo——verifypeer 正常開）
    private function fetch_models_from_api()
    {
        // API key（DeepSeek /models 需要 Bearer token——.env 即時讀取）
        $deepseek_api_key = sc_get_env("DEEPSEEK_API_KEY", "");

        // 判定：冇 key → 唔發請求（回錯誤明細）
        if ($deepseek_api_key === "") {
            return ["error" => "DEEPSEEK_API_KEY is empty (.env)"];
        }

        // curl 請求（RETURNTRANSFER 收 body；連線/整體逾時分開設）
        $curl_handle = curl_init(provider()->get_provider_models_api_url());
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $deepseek_api_key,
        ]);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        // CA bundle（相對 PHP 執行檔——唔靠 php.ini 寫死路徑）
        $cacert_path = sc_bundled_cacert_path();
        if ($cacert_path !== "") {
            curl_setopt($curl_handle, CURLOPT_CAINFO, $cacert_path);
        }

        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 30);
        $response_body = curl_exec($curl_handle);
        $curl_error    = curl_error($curl_handle);
        $curl_status   = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        curl_close($curl_handle);
        
        // 傳輸失敗（錯誤訊息或非 200）→ 回錯誤明細（原文截 300 字）
        if ($response_body === false) {
            return ["error" => "curl failed: " . $curl_error];
        }
        if ($curl_error !== "") {
            return ["error" => "curl error: " . $curl_error];
        }
        if ($curl_status !== 200) {
            return ["error" => "HTTP " . $curl_status . ": " . substr((string)$response_body, 0, 300)];
        }
        
        // 解析 JSON（無 data 鍵或非陣列 → 回錯誤明細）
        $api_data = json_decode($response_body, true);
        if (!isset($api_data["data"])) {
            return ["error" => "unexpected payload: " . substr((string)$response_body, 0, 300)];
        }
        if (!is_array($api_data["data"])) {
            return ["error" => "data is not array"];
        }
        
        
        return $api_data["data"];
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\fetch;

// == sc_ai_providers provider/kilo fetch fetch_models_from_api：域動作（trait——掛入 provider_kilo_Class；檔＝動作） ==
// == 2026-10-06 自 provider/kilo/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait fetch_models_from_api
{
    // 抓取 Kilo models 清單（公開 API——免 key；php.ini 已配 cainfo——verifypeer 正常開）
    private function fetch_models_from_api()
    {
        // curl 請求（RETURNTRANSFER 收 body；連線/整體逾時分開設）
        $curl_handle = curl_init(provider()->get_provider_models_api_url());
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 30);
        $response_body = curl_exec($curl_handle);
        $curl_error    = curl_error($curl_handle);
        $curl_status   = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        curl_close($curl_handle);
        
        // 傳輸失敗（錯誤訊息或非 200）→ null（呼叫端回錯誤）
        if ($response_body === false) {
            return null;
        }
        if ($curl_error !== "") {
            return null;
        }
        if ($curl_status !== 200) {
            return null;
        }
        
        // 解析 JSON（無 data 鍵或非陣列 → null）
        $api_data = json_decode($response_body, true);
        if (!isset($api_data["data"])) {
            return null;
        }
        if (!is_array($api_data["data"])) {
            return null;
        }
        
        
        return $api_data["data"];
    }
}

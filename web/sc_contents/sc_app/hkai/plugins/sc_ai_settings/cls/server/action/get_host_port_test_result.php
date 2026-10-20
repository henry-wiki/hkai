<?php

namespace sc\app\hkai\plugins\sc_ai_settings\server\action;

// == sc_ai_settings server get_host_port_test_result：server 域動作（trait——掛入 server_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat server_Class 分遷入（one file one action） ==

trait get_host_port_test_result
{
    // python server 連線測試（GET /health 固定契約——身份驗證：2xx ＋ {"status":"ok"} 先算 pass；回三態 state／message）
    public function get_host_port_test_result($server_base_url)
    {
        $health_url = $server_base_url . "/health";

        // curl 請求（本機探測——1s 連線逾時、2s 整體逾時）
        $curl_handle = curl_init($health_url);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl_handle, CURLOPT_TIMEOUT, 2);
        $response_body = curl_exec($curl_handle);
        $http_code     = curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE);
        curl_close($curl_handle);

        // 判定：連不上（refused／timeout／DNS 失敗）→ error
        if ($response_body === false) {
            return ["state" => "error", "message" => hkai__i18n()->get("settings.server.test_no_response")];
        }

        // 判定：HTTP 2xx（成功範圍——分段判、單一條件）
        $is_http_success = false;
        if ($http_code >= 200) {
            if ($http_code < 300) {
                $is_http_success = true;
            }
        }

        $response_data = json_decode($response_body, true);

        // 判定：2xx ＋ 回應係 JSON ＋ status ok（三關全過＝本機 server 身份）
        $is_bridge_identity = false;
        if ($is_http_success) {
            if (is_array($response_data)) {
                $is_bridge_identity = (($response_data["status"] ?? "") === "ok");
            }
        }

        // 判定：身份對得上 → pass
        if ($is_bridge_identity) {
            return ["state" => "pass", "message" => hkai__i18n()->get("settings.server.test_ok")];
        }

        // 有回應但唔似本機 server（例：port 被其他服務佔用）→ error
        return ["state" => "error", "message" => hkai__i18n()->get("settings.server.test_occupied") . $http_code . ")"];
    }
}

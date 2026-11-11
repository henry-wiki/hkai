<?PHP

/**
 * 通用 CURL 請求函數（全專案唯一標準——HTTP／HTTPS、GET／POST、headers、憑證開關、自動 User-Agent）
 *
 * @param string $url 請求的 URL
 * @param array $args 請求參數
 *   - 'method'      => 'GET'|'POST'（空＝自動：有 post 資料＝POST，否則 GET）
 *   - 'get'         => array() GET 參數（併入 URL）
 *   - 'post'        => array()|string POST body（array→http_build_query；字串→原樣）
 *   - 'json'        => bool POST 時以 JSON 送出（自動加 Content-Type）
 *   - 'headers'     => array() 自訂 headers（"Name: value" 字串）
 *   - 'user_agent'  => string 自訂 UA（空＝自動 HKAI/<版本>）
 *   - 'ssl_verify'  => bool 是否驗證憑證（預設 true；false 只供開發）
 *   - 'cacert_path' => string 自訂 CA 檔（空＝專案內建 CACert）
 *   - 'timeout'     => int 逾時秒數（預設 30）
 *   - 'connect_timeout'    => int 連線逾時秒數（0＝不設）
 *   - 'connect_timeout_ms' => int 連線逾時毫秒（0＝不設；有值優先於 connect_timeout）
 *   - 'timeout_ms'         => int 整體逾時毫秒（0＝不設；有值取代 timeout）
 *
 * @return array
 *   - 'ok'      => bool 請求是否成功（HTTP < 400 且無傳輸錯誤）
 *   - 'status'  => int HTTP 狀態碼（0＝未收到回應）
 *   - 'body'    => string 原始回應內容
 *   - 'json'    => mixed|null JSON 解析結果（非 JSON＝null）
 *   - 'error'   => string 錯誤訊息（成功＝空字串）
 *   - 'errno'   => int CURL 錯誤碼（0＝無錯）
 *   - 'success' => bool（舊欄位——同 ok）
 *   - 'data'    => mixed（舊欄位——成功＝json 或原文；失敗＝原文）
 */
function sc_curl($url, $args = array())
{
    // 預設值（安全優先——驗證憑證、自動 UA、合理逾時）
    $defaults = array(
        "method"      => "",
        "get"         => array(),
        "post"        => array(),
        "json"        => false,
        "headers"     => array(),
        "user_agent"  => "",
        "ssl_verify"  => true,
        "cacert_path" => "",
        "timeout"     => 30,
        "connect_timeout"    => 0,
        "connect_timeout_ms" => 0,
        "timeout_ms"         => 0,
    );

    $args = array_merge($defaults, $args);

    // GET 參數（併入 URL；無參數＝URL 原樣）
    if (!empty($args["get"])) {
        $get_query = http_build_query($args["get"]);
        $url .= (strpos($url, "?") === false ? "?" : "&") . $get_query;
    }

    // 方法（未指定 → 有 post 資料＝POST，否則 GET）
    $method = strtoupper((string)$args["method"]);

    // 判定：未指定方法 → 由 post 資料推斷
    if ($method === "") {
        $method = empty($args["post"]) ? "GET" : "POST";
    }

    // POST body 與 Content-Type（json＝true 時轉字串）
    $post_body    = "";
    $post_headers = array();

    // 判定：POST → 組 body
    if ($method === "POST") {
        // 判定：post 係字串 → 原樣送出（呼叫端自備格式）
        if (is_string($args["post"])) {
            $post_body = $args["post"];
        } else {
            // 判定：json 模式 → JSON 編碼
            if ($args["json"]) {
                $post_body      = json_encode($args["post"]);
                $post_headers[] = "Content-Type: application/json";
            } else {
                $post_body = http_build_query($args["post"]);
            }
        }
    }

    // User-Agent（未指定 → 自動 HKAI/<版本>）
    $user_agent = (string)$args["user_agent"];

    // 判定：未指定 UA → 自動組
    if ($user_agent === "") {
        $user_agent = sc_curl_user_agent();
    }

    // Headers（呼叫端自訂＋JSON 自動加）
    $request_headers = array_merge((array)$args["headers"], $post_headers);

    // CURL 初始化與基本設定
    $curl_handle = curl_init();

    curl_setopt($curl_handle, CURLOPT_URL, $url);
    curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_handle, CURLOPT_TIMEOUT, (int)$args["timeout"]);

    // 連線逾時（毫秒優先；兩者皆 0＝不設）
    if ((int)$args["connect_timeout_ms"] > 0) {
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT_MS, (int)$args["connect_timeout_ms"]);
    } elseif ((int)$args["connect_timeout"] > 0) {
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, (int)$args["connect_timeout"]);
    }

    // 整體逾時（毫秒版有值 → 取代秒版）
    if ((int)$args["timeout_ms"] > 0) {
        curl_setopt($curl_handle, CURLOPT_TIMEOUT_MS, (int)$args["timeout_ms"]);
    }
    curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl_handle, CURLOPT_MAXREDIRS, 5);
    curl_setopt($curl_handle, CURLOPT_USERAGENT, $user_agent);
    curl_setopt($curl_handle, CURLOPT_CUSTOMREQUEST, $method);

    // 判定：POST → 落 body
    if ($method === "POST") {
        curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $post_body);
    }

    // 判定：有 headers → 送出
    if (!empty($request_headers)) {
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $request_headers);
    }

    $is_https_url = strpos($url, "https://") === 0;

    // 判定：HTTPS → 處理憑證驗證
    if ($is_https_url) {
        // 判定：要驗證 → 開驗證＋指定 CA
        if ($args["ssl_verify"]) {
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);

            $cacert_path = (string)$args["cacert_path"];

            // 判定：未指定 CA → 用專案內建／資料根憑證
            if ($cacert_path === "") {
                $detected_cacert_path = get_ca_certificate_path();

                // 判定：搵到憑證 → 用佢
                if ($detected_cacert_path !== false) {
                    $cacert_path = $detected_cacert_path;
                }
            }

            // 判定：有 CA 路徑 → 設定 CAINFO
            if ($cacert_path !== "") {
                curl_setopt($curl_handle, CURLOPT_CAINFO, $cacert_path);
            }
        } else {
            // 關閉驗證（只供開發環境——生產禁用）
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
        }
    }

    // 執行請求並取回應資訊
    $response_body = curl_exec($curl_handle);
    $curl_info     = curl_getinfo($curl_handle);
    $curl_error    = curl_error($curl_handle);
    $curl_errno    = curl_errno($curl_handle);

    curl_close($curl_handle);

    $status_code = isset($curl_info["http_code"]) ? (int)$curl_info["http_code"] : 0;

    // 回傳結構（新欄位＋舊欄位並存——呼叫端可逐步遷移）
    $result            = array();
    $result["ok"]      = false;
    $result["success"] = false;
    $result["status"]  = $status_code;
    $result["body"]    = "";
    $result["data"]    = null;
    $result["json"]    = null;
    $result["error"]   = "";
    $result["errno"]   = $curl_errno;

    // 判定：傳輸層失敗（連線／憑證／逾時）→ 直接回錯
    if ($curl_errno !== 0 || $response_body === false) {
        $result["error"] = "CURL Error (" . $curl_errno . "): " . $curl_error;

        return $result;
    }

    $result["body"] = (string)$response_body;
    $result["data"] = (string)$response_body;

    // HTTP 狀態（>=400 記錯誤；body 照留——呼叫端可讀原因）
    if ($status_code >= 400) {
        $result["error"] = "HTTP Error: " . $status_code;
    } else {
        $result["ok"]      = true;
        $result["success"] = true;
    }

    // JSON 解析（合法 JSON 就落 json 鍵）
    $json_value = json_decode($result["body"], true);

    // 判定：合法 JSON → 落 json（成功時 data 用解析結果——與舊版一致）
    if (json_last_error() === JSON_ERROR_NONE) {
        $result["json"] = $json_value;

        // 判定：請求成功 → data 用解析結果
        if ($result["ok"]) {
            $result["data"] = $json_value;
        }
    }

    return $result;
}

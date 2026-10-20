<?PHP

/**
 * 通用 CURL 請求函數
 * 
 * @param string $url 請求的 URL (支援 https)
 * @param array $args 請求參數
 *   - 'get'     => array() GET 參數
 *   - 'post'    => array() POST 參數
 *   - 'headers' => array() 自定義 headers
 *   - 'timeout' => int 超時秒數 (預設 30)
 *   - 'json'    => bool 是否以 JSON 格式發送 POST (預設 false)
 * 
 * @return array 回傳結果
 *   - 'success' => bool 請求是否成功
 *   - 'data'    => mixed 回應內容
 *   - 'info'    => array CURL 資訊
 *   - 'error'   => string 錯誤訊息
 */
function sc_curl($url, $args = array()) 
{
    // 預設值設定
    $defaults = array(
        'get'     => array(),
        'post'    => array(),
        'headers' => array(),
        'timeout' => 30,
        'json'    => false,
		'ssl_verify' => true,
    );
    
    // 合併參數
    $args = array_merge($defaults, $args);
    
    // 處理 GET 參數
    if (!empty($args['get'])) {
        $get_params = http_build_query($args['get']);
        $url .= (strpos($url, '?') === false ? '?' : '&') . $get_params;
    }
    
    // 初始化 CURL
    $ch = curl_init();
    
    // 基本設定
    curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    
    // HTTPS 相關設定
    if (strpos($url, 'https://') === 0) {
        if ($args['ssl_verify']) {
            // 方法 A: 使用系統 CA 憑證
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            
            // 尋找可用的 CA 憑證
            $cacert_paths = array();
            
            // 1. 使用自定義路徑
            if (!empty($args['cacert_path']) && file_exists($args['cacert_path'])) {
                $cacert_paths[] = $args['cacert_path'];
            }
			
			// -------------------------------------------------------------------------------
			// 2. 同目錄下的 cacert.pem
            $local_cert = update_ca_certificate();
			
			
            if (file_exists($local_cert)) {
                $cacert_paths[] = $local_cert;
            }
            
            // 使用第一個找到的憑證
            if (!empty($cacert_paths)) {
				curl_setopt($ch, CURLOPT_CAINFO, $cacert_paths[0]);
			} 
			
        } else {
            // 關閉 SSL 驗證（僅開發環境使用）
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
    }
    
    // 處理 POST 參數
    if (!empty($args['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        
        if ($args['json']) {
            // JSON 格式
            $post_data = json_encode($args['post']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            // 自動加入 JSON header
            $args['headers'][] = 'Content-Type: application/json';
            $args['headers'][] = 'Content-Length: ' . strlen($post_data);
        } else {
            // 一般 POST 格式
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args['post']));
        }
    }
    
    // 處理自定義 Headers
    if (!empty($args['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);
    }
   
    
    // 執行請求
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    
    // 關閉 CURL
    curl_close($ch);
    
    // 準備回傳結果
    $result = array(
        'success' => false,
        'data'    => null,
        //'info'    => $info,
        'error'   => ''
    );
    
    // 判斷執行結果
    if ($errno) {
        $result['error'] = "CURL Error ({$errno}): {$error}";
    } elseif ($info['http_code'] >= 400) {
        $result['error'] = "HTTP Error: {$info['http_code']}";
        $result['data'] = $response;
    } else {
        $result['success'] = true;
        $result['data'] = $response;
        
        // 嘗試解析 JSON
        $json_decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['data'] = $json_decoded;
        }
    }
    
    return $result;
}

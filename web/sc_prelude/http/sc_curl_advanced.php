<?PHP

/**
 * 進階版 - 支援更多功能
 */
function sc_curl_advanced($url, $args = array()) {
    $defaults = array(
        'method'        => 'GET',      // 請求方法: GET, POST, PUT, DELETE, PATCH
        'get'           => array(),
        'post'          => array(),
        'headers'       => array(),
        'timeout'       => 30,
        'json'          => false,
        'cookie_file'   => '',         // Cookie 檔案路徑
        'user_agent'    => 'SC-CURL/1.0',
        'referer'       => '',
        'proxy'         => '',         // 代理伺服器
        'auth'          => array(),    // ['username', 'password']
        'ssl_verify'    => true,       // 是否驗證 SSL
        'return_header' => false,      // 是否回傳 response headers
        'files'         => array()     // 上傳檔案 ['field_name' => 'file_path']
    );
    
    $args = array_merge($defaults, $args);
    
    // GET 參數處理
    if (!empty($args['get'])) {
        $get_params = http_build_query($args['get']);
        $url .= (strpos($url, '?') === false ? '?' : '&') . $get_params;
    }
    
    $ch = curl_init();
    
    // 基本設定
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $args['timeout']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, $args['user_agent']);
    
    // 請求方法
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($args['method']));
    
    // HTTPS 設定
    if (strpos($url, 'https://') === 0) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $args['ssl_verify']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $args['ssl_verify'] ? 2 : 0);
    }
    
    // POST 資料或檔案上傳
    if (!empty($args['post']) || !empty($args['files'])) {
        if (!empty($args['files'])) {
            // 檔案上傳
            $post_data = $args['post'];
            foreach ($args['files'] as $field => $filepath) {
                if (file_exists($filepath)) {
                    $post_data[$field] = new CURLFile($filepath);
                }
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        } elseif ($args['json']) {
            // JSON 格式
            $post_data = json_encode($args['post']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            $args['headers'][] = 'Content-Type: application/json';
        } else {
            // 一般 POST
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args['post']));
        }
    }
    
    // Headers
    if (!empty($args['headers'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $args['headers']);
    }
    
    // Cookie
    if (!empty($args['cookie_file'])) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $args['cookie_file']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $args['cookie_file']);
    }
    
    // Referer
    if (!empty($args['referer'])) {
        curl_setopt($ch, CURLOPT_REFERER, $args['referer']);
    }
    
    // Proxy
    if (!empty($args['proxy'])) {
        curl_setopt($ch, CURLOPT_PROXY, $args['proxy']);
    }
    
    // HTTP 認證
    if (!empty($args['auth'])) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $args['auth'][0] . ':' . $args['auth'][1]);
    }
    
    // 是否回傳 headers
    if ($args['return_header']) {
        curl_setopt($ch, CURLOPT_HEADER, true);
    }
    
   
    
    // 執行
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    
    curl_close($ch);
    
    // 分離 header 和 body
    $header_text = '';
    $body = $response;
    
    if ($args['return_header'] && $response) {
        $header_size = $info['header_size'];
        $header_text = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
    }
    
    // 準備結果
    $result = array(
        'success'       => !$errno && $info['http_code'] < 400,
        'data'          => $body,
        'headers'       => $header_text,
        'info'          => $info,
        'error'         => $errno ? "CURL Error ({$errno}): {$error}" : '',
        'http_code'     => $info['http_code']
    );
    
    // 嘗試 JSON 解析
    if ($result['success']) {
        $json_decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['data'] = $json_decoded;
        }
    }
    
    return $result;
}

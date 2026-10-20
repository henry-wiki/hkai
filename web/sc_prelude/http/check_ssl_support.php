<?PHP

function check_ssl_support() {
    if (!function_exists('curl_version')) {
        return "cURL 擴展未安裝";
    }
    
    $curl_info = curl_version();
    
    echo "=== cURL SSL 支持檢查 ===\n";
    echo "cURL 版本: " . $curl_info['version'] . "\n";
    echo "SSL 庫: " . (isset($curl_info['ssl_version']) ? $curl_info['ssl_version'] : '未知') . "\n";
    
    // 檢查支持的協議
    $ssl_protocols = array_intersect(['https', 'ftps', 'smtps'], $curl_info['protocols']);
    echo "支持的 SSL 協議: " . (empty($ssl_protocols) ? '無' : implode(', ', $ssl_protocols)) . "\n";
    
    // 檢查特性
    $has_ssl = ($curl_info['features'] & CURL_VERSION_SSL) ? '是' : '否';
    echo "支持 SSL: $has_ssl\n";
    
    // 檢查 OpenSSL 版本（如果可用）
    if (function_exists('openssl_version')) {
        $openssl_version = openssl_version();
        echo "OpenSSL 版本: $openssl_version\n";
    } else {
        echo "OpenSSL 擴展: 未安裝\n";
    }
    
    // 檢查 PHP.ini 設置
    echo "curl.cainfo: " . (ini_get('curl.cainfo') ?: '未設置') . "\n";
    echo "openssl.cafile: " . (ini_get('openssl.cafile') ?: '未設置') . "\n";
	
	
	
}

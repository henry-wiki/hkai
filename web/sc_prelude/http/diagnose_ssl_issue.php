<?PHP

function diagnose_ssl_issue($url) 
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_VERBOSE, true);  // 詳細輸出
    curl_setopt($ch, CURLOPT_CERTINFO, true);  // 憑證資訊
    
    // 嘗試不同的設定
    $cacert_path = SC_DIR_FRAMEWORK . "/../config" . '/ssl/cacert.pem';
    if (file_exists($cacert_path)) {
        echo "CA 檔案存在: " . $cacert_path . "\n";
        echo "檔案大小: " . filesize($cacert_path) . " bytes\n";
        echo "檔案權限: " . substr(sprintf('%o', fileperms($cacert_path)), -4) . "\n";
        curl_setopt($ch, CURLOPT_CAINFO, $cacert_path);
    } else {
        echo "CA 檔案不存在！\n";
    }
    
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $result = curl_exec($ch);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    $info = curl_getinfo($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    echo "錯誤碼: $errno\n";
    echo "錯誤訊息: $error\n";
    echo "詳細日誌:\n$verboseLog\n";
    echo "SSL 版本: " . $info['ssl_version'] . "\n";
    
    curl_close($ch);
}

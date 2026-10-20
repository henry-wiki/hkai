<?PHP

/**
 * 自動下載並更新 CA 憑證
 */
function update_ca_certificate() {
    $cacert_url = 'https://curl.se/ca/cacert.pem';
    $cacert_path = SC_DIR_FRAMEWORK . "/../config" . '/ssl/cacert.pem';
    
    // 檢查憑證是否需要更新（30天）
    if (file_exists($cacert_path)) {
        $file_age = time() - filemtime($cacert_path);
        if ($file_age < 30 * 24 * 60 * 60) {
            return $cacert_path;
        }
    }
    
    // 下載新憑證
    $cert_content = file_get_contents($cacert_url);
	
	
    if ($cert_content !== false) {
        file_put_contents($cacert_path, $cert_content);
        return $cacert_path;
    }
	
	
	
    
    return false;
}

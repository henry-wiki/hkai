<?php
namespace sc\framework\foundation\http\middleware;

// == sc_validate_post_size_middleware：POST 大小檢查（對應 Laravel ValidatePostSize） ==
// == 功用：請求體超過 php post_max_size 時回 413（例：上傳超大檔案被伺服器截斷——及早拒絕） ==

class sc_validate_post_size_middleware_Class
{
    private static $instance = null;
    
    public static function getInstance()
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
    }
    
    // 管線執行點：CONTENT_LENGTH 超限 → 413
    public function handle()
    {
        // 判定：非 POST 請求（CGI 唔提供）→ 當 0（唔直接讀未定義索引）
        $contentLength = isset($_SERVER["CONTENT_LENGTH"]) ? $_SERVER["CONTENT_LENGTH"] : 0;
        $maxPostSize = @ini_get("post_max_size");
        
        // post_max_size 無法解析（如 "8M"）→ 取數字位元組值（8M = 8388608）
        $maxPostBytes = $this->parse_bytes($maxPostSize);
        
        // 請求體超過上限 → 413（PHP 已截斷，直接拒絕）
        if (@$contentLength && $maxPostBytes && $contentLength > $maxPostBytes) {
            throw new \sc\framework\foundation\http\exception\sc_http_exception(413, "Request Entity Too Large");
        }
    }
    
    // 解析大小字串為位元組（"8M" → 8388608；支援 K/M/G）
    private function parse_bytes($sizeString)
    {
        if (!@$sizeString) {
            return 0;
        }
        
        $sizeValue = (int)$sizeString;
        $sizeUnit = strtoupper(substr(sc_trim($sizeString), -1));
        
        // 依單位放大（K=1024、M=1024²、G=1024³）
        if ($sizeUnit === "K") {
            $sizeValue *= 1024;
        } elseif ($sizeUnit === "M") {
            $sizeValue *= 1024 * 1024;
        } elseif ($sizeUnit === "G") {
            $sizeValue *= 1024 * 1024 * 1024;
        }
        
        return $sizeValue;
    }
}

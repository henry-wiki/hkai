<?php
namespace sc\framework\foundation\http\middleware;

// == sc_throttle_middleware：API 限流（對應 Laravel throttle:api——RateLimiter 依 IP 計數） ==
// == 行為：每 IP 每分鐘最多 $maxRequests 次（Laravel 默認 60），超過 → 429 Too Many Requests ==
// == 儲存：SC_TMP_DIR/throttle/{ip}.json（每分鐘一窗口——固定窗口計數） ==

class sc_throttle_middleware_Class
{
    private static $instance = null;
    
    // 每分鐘允許的最大請求數（Laravel throttle:api 默認 60）
    private $max_requests_per_minute = 60;
    
    // 限流計數檔目錄
    private $throttle_dir;
    
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
        $this->throttle_dir = SC_TMP_DIR . "/throttle";
    }
    
    // 管線入口：依 IP 檢查當下分鐘窗口請求數
    public function handle()
    {
        // 取得客戶 IP（無 → 不限流）
        $clientIp = sc_request()->ip();
        if (!$clientIp) {
            return;
        }
        
        // 目錄不存在 → 建立（限流記錄用）
        if (!@is_dir($this->throttle_dir)) {
            @mkdir($this->throttle_dir, SC_CHMOD, true);
        }
        
        // 窗口 key：分鐘為單位（固定窗口——每分鐘重置）
        $windowKey = date("YmdHi");
        $throttleFile = $this->throttle_dir . "/" . $clientIp . ".json";
        
        // 讀取現有計數（檔不存在 → 空窗口）
        $windowData = [];
        if (@file_exists($throttleFile)) {
            $windowData = json_decode(@file_get_contents($throttleFile), true);
        }
        if (!@is_array($windowData)) {
            $windowData = [];
        }
        
        // 窗口不同（跨分鐘）→ 重置計數
        if ((isset($windowData["window"]) ? $windowData["window"] : null) !== $windowKey) {
            $windowData = [
                "window" => $windowKey,
                "count"  => 0,
            ];
        }
        
        // 已達上限 → 429 中斷
        if ($windowData["count"] >= $this->max_requests_per_minute) {
            throw new \sc\framework\foundation\http\exception\sc_http_exception(429, "Too Many Requests");
        }
        
        // 計數 +1 並寫回
        $windowData["count"]++;
        @file_put_contents($throttleFile, json_encode($windowData));
    }
}

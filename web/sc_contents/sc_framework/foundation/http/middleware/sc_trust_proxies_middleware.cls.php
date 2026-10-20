<?php
namespace sc\framework\foundation\http\middleware;

// == sc_trust_proxies_middleware：信任代理設定（對應 Laravel TrustProxies） ==
// == 功用：告訴框架哪些代理 IP 可信，取得真實客戶 IP（例：nginx 反向代理後拿真實 IP） ==
// == 目前為設定骨架：未來填入可信代理清單後，sc_request()->ip() 可回真實 IP ==

class sc_trust_proxies_middleware_Class
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
    
    // 管線執行點（目前無操作——留待設定可信代理後生效）
    public function handle()
    {
        // 未來：設定可信代理（$_SERVER["REMOTE_ADDR"] 對照）後，IP 取用才準確
    }
}

<?php
namespace sc\framework\foundation\http\middleware;

// == sc_csrf_middleware：CSRF 請求驗證（管線零件——掛載於 sc_dispatcher） ==

class sc_csrf_middleware_Class
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
    
    // 管線執行點（sc_dispatcher 依序呼叫）
    public function handle()
    {
        // 非 POST 請求不需 CSRF 驗證（GET 等為讀取）
        if (!IsPost()) {
            return;
        }
        
        $clientToken = get_client_CSRF();
        
        // token 缺失或不匹配 → 拒絕請求（403 例外——統一錯誤管道）
        // 驗證後保留（clear=false）：同 session 內 token 重用——頁面 meta 一次輸出，前端多次 POST 不需刷新
        if (!CSRF_verify($clientToken, false)) {
            throw new \sc\framework\foundation\http\exception\sc_http_exception(403, "Request Error : Invalid CSRF token");
        }
    }
}

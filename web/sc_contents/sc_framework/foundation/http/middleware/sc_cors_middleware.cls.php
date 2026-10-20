<?php
namespace sc\framework\foundation\http\middleware;

// == sc_cors_middleware：跨域支援（對應 Laravel HandleCors） ==
// == 功用：允許瀏覽器跨網域呼叫（例：前端在別台伺服器，要呼叫本 API 需 CORS header） ==
// == 目前設定：允許所有來源（開發用）——正式環境請收窄 origin 清單 ==

class sc_cors_middleware_Class
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
    
    // 管線執行點：加上 CORS 回應 header
    public function handle()
    {
        // 允許所有來源跨域（開發用——正式環境改為白名單）
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
        
        // 預檢請求（OPTIONS）直接回應 204——瀏覽器跨域前會先發預檢
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(204);
            exit;
        }
    }
}

<?php
namespace sc\framework\foundation\http\middleware;

// == sc_maintenance_middleware：維修模式（對應 Laravel PreventRequestsDuringMaintenance） ==
// == 功用：停服更新時回 503「暫停服務」（例：資料庫升級中，先擋住全部請求） ==
// == 觸發：存在標記檔 _tmp/maintenance（php artisan down 的簡化版——手動建檔/刪檔） ==

class sc_maintenance_middleware_Class
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
    
    // 管線執行點：維修標記存在 → 503 停服
    public function handle()
    {
        $maintenanceFile = SC_DOCUMENT_ROOT . "/_tmp/maintenance";
        
        // 維修標記存在 → 503 停服（開發中無標記檔，不觸發）
        if (file_exists($maintenanceFile)) {
            throw new \sc\framework\foundation\http\exception\sc_http_exception(503, "Service Unavailable : maintenance mode");
        }
    }
}

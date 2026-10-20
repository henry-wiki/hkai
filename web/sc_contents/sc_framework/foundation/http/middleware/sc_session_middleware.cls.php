<?php
namespace sc\framework\foundation\http\middleware;

// == sc_session_middleware：session 啟動（對應 Laravel StartSession——app 組 middleware） ==
// == 功用：請求處理前啟動 session（登入狀態/語言選擇/CSRF token 等跨請求狀態） ==
// == 例：使用者登入後，後續每個請求都有 $_SESSION 可用 ==
// == 注意：Laravel 的 StartSession 在 app 組（api 組無 session）——本版掛全局（plugins 未歸組） ==
// ==       未來 plugins 歸 app 組後，移入 app 組掛載（api 免 session——Laravel 對齊） ==

class sc_session_middleware_Class
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
    
    // 管線執行點：session 設定 + 啟動
    public function handle()
    {
        // driver/save_path 從 settings 讀（預設 file + SC_TMP_DIR/sessions）
        $sessionConfig = array(
            "driver"    => sc_settings()->get("session_driver", "file"),
            "save_path" => sc_settings()->get("session_save_path", SC_TMP_DIR . "/sessions"),
        );
        
        // file driver → 設定儲存路徑（sc_session_file 為 PHP session handler）
        if ($sessionConfig["driver"] === "file") {
            sc_session_file()->set_session_save_path($sessionConfig["save_path"]);
        }
        
        sc_session()->set_driver($sessionConfig["driver"]);
        sc_session()->start();
    }
}

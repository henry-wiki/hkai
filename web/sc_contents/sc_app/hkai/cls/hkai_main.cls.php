<?php

namespace sc\app\hkai\cls;

// == hkai_main_Class：hkai app 層主 class（singleton——由 bridging hkai__main() 取得） ==
// == initial()：app 啟動時由 __init__.php 呼叫——初始化 sqlite 連線路徑、建立連線（資料表結構＝setup 安裝側建立） ==
// == 核心相關（設定/常數/冷啟動/心跳）已移至 hkai_core_Class（bridging hkai__core()） ==

class hkai_main_Class
{
    private static $instance = null;
    
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {}
    
    // 初始化（__init__.php 載入語境時呼叫一次——sqlite 路徑注入 + 建立連線；資料表結構＝setup 安裝側建立）
    public function initial($controller)
    {
        // 注入 daemon class 目錄（同一 controller——daemon 設定/冷啟動所需）
        hkai__core()->initial($controller);
        
        // 注入 sqlite 路徑（值＝單一來源 sc_config/load.php storage.database_file——config/database.php 的 database 留空由本處注入）
        $databaseFilePath = sc_get_config("storage.database_file");
        sc_set_config("database.connections.sqlite.database", $databaseFilePath);
        
        // 建立並快取連線（其他 plugin 以 sc_db()->connection("sqlite") 取回同一實例）
        sc_db()->connection("sqlite");
    }
    
}

<?php

// == sc_app/dbviewer/cls/dbviewer_core.cls.php：dbviewer app 層核心 class（singleton——bridging dbviewer__core()） ==
// == 職責：持有本 app 的設定（sqlite 檔絕對路徑——__init__.php 寫死注入，class 零寫死） ==

namespace sc\app\dbviewer\cls;

class dbviewer_core_Class
{
    private static $instance = null;
    
    // sqlite 檔絕對路徑（檢視目標庫）
    private $sqlite_file = null;
    
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
    
    // 注入設定（__init__.php 載入語境時呼叫一次——key 與屬性同名）
    public function initial_configs($configs)
    {
        // sqlite 檔路徑（檢視目標庫）
        $this->sqlite_file = $configs["sqlite_file"];
    }
    
    // 取得 sqlite 檔路徑
    public function get_sqlite_file()
    {
        return $this->sqlite_file;
    }
}

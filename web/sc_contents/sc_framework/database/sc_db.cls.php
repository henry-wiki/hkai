<?php
namespace sc\framework\database;

// == sc_db：資料庫管理器（對應 Laravel DatabaseManager——依 sc_config/assets/database.php 建立/取回多連線） ==
// == 使用：sc_db()->connection('sqlite')->select(...)；無名稱 = default 連線（config database.default） ==
// == __call 轉發未定義方法到 default 連線（Laravel DatabaseManager __call 對應——既有呼叫零改動） ==

class sc_db_Class
{
    private static $instance = null;
    
    // 連線實例清單（name → Connection——每請求生命週期）
    private $connections = [];
    
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
    
    // 依名稱取得連線（不存在時建立；無名稱用 default）
    public function connection($name = null)
    {
        if ($name === null) {
            $name = $this->get_default_connection_name();
        }
        
        // 已建立 → 直接回傳
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        
        // 依 config 建立並快取
        $connection = $this->make_connection($name);
        $this->connections[$name] = $connection;
        
        return $connection;
    }
    
    // 依 config 建立連線
    private function make_connection($name)
    {
        $config = $this->get_connection_config($name);
        $factory = new sc_connection_factory_Class();
        return $factory->make($config);
    }
    
    // 讀取連線設定（sc_config/assets/database.php connections）
    private function get_connection_config($name)
    {
        $connections = sc_get_config("database.connections");
        
        // 連線未設定 → 拒絕
        if (!isset($connections[$name])) {
            throw new \InvalidArgumentException("Database connection not configured: " . $name);
        }
        
        return $connections[$name];
    }
    
    // default 連線名（config database.default）
    private function get_default_connection_name()
    {
        return sc_get_config("database.default", "sqlite");
    }
    
    // 轉發未定義方法到 default 連線（對應 Laravel DatabaseManager __call）
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->connection(), $method], $parameters);
    }
    
}

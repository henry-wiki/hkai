<?php
namespace sc\framework\database\connectors;

// == sc_connector：Connector 基底（對應 Laravel Connector——PDO 連線建立 + 預設 options） ==
// == 子類（mysql/sqlite）只負責 DSN 差異與連線後設定——連線建立邏輯共用於此 ==

class sc_connector_Class
{
    // PDO 預設選項（對應 Laravel Connector 預設——例外模式、關閉模擬 prepare、關聯陣列）
    protected function get_default_options($config)
    {
        return [
            \PDO::ATTR_CASE => \PDO::CASE_NATURAL,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_NATURAL,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }
    
    // 建立 PDO 連線（dsn + username/password + options）
    protected function create_connection($dsn, $config)
    {
        $username = isset($config["username"]) ? $config["username"] : null;
        $password = isset($config["password"]) ? $config["password"] : null;
        $options  = $this->get_default_options($config);
        
        return new \PDO($dsn, $username, $password, $options);
    }
}

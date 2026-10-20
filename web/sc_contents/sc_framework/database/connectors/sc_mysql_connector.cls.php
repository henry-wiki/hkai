<?php
namespace sc\framework\database\connectors;

// == sc_mysql_connector：MySQL 連線（DSN host/port + 連線後 charset 設定——對應 Laravel MySqlConnector） ==

class sc_mysql_connector_Class extends sc_connector_Class
{
    // 建立 MySQL 連線（組 DSN → 建立 PDO → charset 設定）
    public function connect($config)
    {
        $dsn = "mysql:host=" . $config["host"] . ";dbname=" . $config["database"];
        
        // 有指定 port 時加入 DSN
        if (isset($config["port"]) && $config["port"] !== "") {
            $dsn .= ";port=" . $config["port"];
        }
        
        $connection = $this->create_connection($dsn, $config);
        
        // 連線後設定 charset（有指定時）
        if (isset($config["charset"]) && $config["charset"] !== "") {
            $connection->exec("set names '" . $config["charset"] . "'");
        }
        
        return $connection;
    }
}

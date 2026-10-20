<?php
namespace sc\framework\database\connectors;

// == sc_pgsql_connector：PostgreSQL 連線（DSN host/port/dbname/charset——對應 Laravel PostgresConnector） ==

class sc_pgsql_connector_Class extends sc_connector_Class
{
    // 建立 PostgreSQL 連線（組 DSN → 建立 PDO）
    public function connect($config)
    {
        $dsn = "pgsql:host=" . $config["host"] . ";dbname=" . $config["database"];
        
        // 有指定 port 時加入 DSN
        if (isset($config["port"]) && $config["port"] !== "") {
            $dsn .= ";port=" . $config["port"];
        }
        
        // 有指定 charset 時加入 DSN
        if (isset($config["charset"]) && $config["charset"] !== "") {
            $dsn .= ";charset=" . $config["charset"];
        }
        
        return $this->create_connection($dsn, $config);
    }
}

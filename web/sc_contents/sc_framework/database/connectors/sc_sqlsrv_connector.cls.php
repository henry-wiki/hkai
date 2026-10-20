<?php
namespace sc\framework\database\connectors;

// == sc_sqlsrv_connector：SQL Server 連線（DSN Server/Database——對應 Laravel SqlServerConnector） ==

class sc_sqlsrv_connector_Class extends sc_connector_Class
{
    // 建立 SQL Server 連線（組 DSN → 建立 PDO）
    public function connect($config)
    {
        $dsn = "sqlsrv:Server=" . $config["host"] . "," . $config["port"] . ";Database=" . $config["database"];
        
        return $this->create_connection($dsn, $config);
    }
}

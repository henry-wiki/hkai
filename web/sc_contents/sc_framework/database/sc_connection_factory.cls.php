<?php
namespace sc\framework\database;

use sc\framework\database\connectors\sc_mysql_connector_Class;
use sc\framework\database\connectors\sc_sqlite_connector_Class;
use sc\framework\database\connectors\sc_pgsql_connector_Class;
use sc\framework\database\connectors\sc_sqlsrv_connector_Class;
use sc\framework\database\grammars\sc_mysql_grammar_Class;
use sc\framework\database\grammars\sc_sqlite_grammar_Class;
use sc\framework\database\grammars\sc_pgsql_grammar_Class;
use sc\framework\database\grammars\sc_sqlsrv_grammar_Class;

// == sc_connection_factory：依 config driver 建立連線（對應 Laravel ConnectionFactory——Connector → Connection） ==

class sc_connection_factory_Class
{
    // 依連線設定建立 Connection（Connector 建 PDO → Connection 包裝 + grammar + schema 確保 + 重連閉包）
    public function make($config)
    {
        $driver = $config["driver"];
        $connector = $this->create_connector($driver);
        $grammar = $this->create_grammar($driver);
        
        // 建立 PDO（DSN 差異由各 connector 封裝）
        $pdo = $connector->connect($config);
        
        $connection = new sc_connection_Class($pdo, $config, $grammar);
        
        // 注入重連閉包（斷線時以同一 connector/config 重建 PDO）
        $connection->set_reconnector(function () use ($connector, $config) {
            return $connector->connect($config);
        });
        
        return $connection;
    }
    
    // 依 driver 建立對應 Connector
    private function create_connector($driver)
    {
        // mysql → MySQL connector
        if ($driver === "mysql") {
            return new sc_mysql_connector_Class();
        }
        // sqlite → SQLite connector
        if ($driver === "sqlite") {
            return new sc_sqlite_connector_Class();
        }
        // pgsql → PostgreSQL connector
        if ($driver === "pgsql") {
            return new sc_pgsql_connector_Class();
        }
        // sqlsrv → SQL Server connector
        if ($driver === "sqlsrv") {
            return new sc_sqlsrv_connector_Class();
        }
        
        // 不支援的 driver → 拒絕
        throw new \InvalidArgumentException("Unsupported database driver: " . $driver);
    }
    
    // 依 driver 建立對應 Grammar
    private function create_grammar($driver)
    {
        // mysql → MySQL grammar
        if ($driver === "mysql") {
            return new sc_mysql_grammar_Class();
        }
        // sqlite → SQLite grammar
        if ($driver === "sqlite") {
            return new sc_sqlite_grammar_Class();
        }
        // pgsql → PostgreSQL grammar
        if ($driver === "pgsql") {
            return new sc_pgsql_grammar_Class();
        }
        // sqlsrv → SQL Server grammar
        if ($driver === "sqlsrv") {
            return new sc_sqlsrv_grammar_Class();
        }
        
        // 不支援的 driver → 拒絕
        throw new \InvalidArgumentException("Unsupported database driver: " . $driver);
    }
}

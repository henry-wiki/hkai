<?php
namespace sc\framework\database\connectors;

// == sc_sqlite_connector：SQLite 連線（路徑檢查 + :memory: 支援——對應 Laravel SQLiteConnector） ==

class sc_sqlite_connector_Class extends sc_connector_Class
{
    // 建立 SQLite 連線（:memory: 或實體檔路徑）
    public function connect($config)
    {
        $databasePath = $config["database"];
        
        // 記憶體資料庫（測試/暫存用——僅單一連線）
        if ($databasePath === ":memory:") {
            return $this->create_connection("sqlite::memory:", $config);
        }
        
        $realPath = realpath($databasePath);
        
        // 檔案不存在 → 拒絕連線（避免誤建空庫）
        if ($realPath === false) {
            throw new \InvalidArgumentException("SQLite database does not exist: " . $databasePath);
        }
        
        $connection = $this->create_connection("sqlite:" . $realPath, $config);

        // 連線後固定設定（SQLite 逐連線生效——必須每次連線落）：
        // ① journal_mode=WAL：持久機制再確認（防被轉返 rollback journal）
        // ② synchronous=NORMAL：WAL 之下安全咁降低 fsync 頻率
        // ③ busy_timeout=5000：鎖等待上限 5 秒（取代 PDO 默認 60000——唔再靜默等 60 秒）
        $connection->exec("PRAGMA journal_mode=WAL");
        $connection->exec("PRAGMA synchronous=NORMAL");
        $connection->exec("PRAGMA busy_timeout=5000");

        return $connection;
    }
}

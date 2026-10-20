<?php
// == sc_config/assets/database.php：資料庫連線集中設定（對齊 Laravel config/database.php——default + connections 多連線） ==
// == 由 sc_config() 自動掃描載入；連線以名稱取（無名稱＝default） ==

return [
    "default" => "sqlite",
    
    "connections" => [
        
        "sqlite" => [
            "driver" => "sqlite",
            // 路徑留空——由 app 層依執行語境注入（sc_storage/{app 名}/hkai_chat_data.sqlite；多 app 各自自己的庫）
            "database" => "",
            // schema 不在此宣告——由 app 層集中建立
        ],
        
        "mysql" => [
            "driver"   => "mysql",
            "host"     => "127.0.0.1",
            "port"     => "3306",
            "database" => "",
            "username" => "root",
            "password" => "",
            "charset"  => "utf8mb4",
        ],
        
        "pgsql" => [
            "driver"   => "pgsql",
            "host"     => "127.0.0.1",
            "port"     => "5432",
            "database" => "",
            "username" => "postgres",
            "password" => "",
            "charset"  => "utf8",
        ],
        
        "sqlsrv" => [
            "driver"   => "sqlsrv",
            "host"     => "localhost",
            "port"     => "1433",
            "database" => "",
            "username" => "sa",
            "password" => "",
        ],
        
    ],
];

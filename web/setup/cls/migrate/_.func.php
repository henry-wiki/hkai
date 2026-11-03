<?php

namespace sc\setup\migrate;

// == HKAI setup migrate 域短名（_.func.php·migrate 域——bridging 專檔）
// == 職責：get_migrate_env()＝搬遷 .env 讀取器（路徑由呼叫端指定——同一支類別讀 staging／舊安裝）
// == 載入：使用前 require_once（本程式無 autoloader——顯式載入、零註冊）

/**
 * 搬遷 .env 讀取器（指定路徑；同一路徑重用同一實例）
 *
 * @param string $env_file .env 絕對路徑
 *
 * @return \sc\setup\migrate\migrate_env_Class
 */
function get_migrate_env($env_file)
{
    require_once __DIR__ . "/migrate_env.php";

    // 同一路徑重用（避免重複 parse）
    static $migrate_env_instances = [];

    // 判定：已建過 → 回舊實例
    if (isset($migrate_env_instances[$env_file])) {
        return $migrate_env_instances[$env_file];
    }

    $migrate_env_instances[$env_file] = new migrate_env_Class($env_file);

    return $migrate_env_instances[$env_file];
}

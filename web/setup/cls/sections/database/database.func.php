<?php

namespace sc\setup\sections\database;

// == HKAI setup sections/database internal 短名（database.func.php·database 格）：同 namespace 直接叫（setup()／db_ready()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：database 格本體（database.php）頂載（本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——database 格 → setup 根；例：setup()->state()->get_setting_setup_process(...)）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

/**
 * 檢查單元：db_ready（按需 require 單元檔——同 namespace 直取）
 *
 * @return \sc\setup\sections\database\database_db_ready_Class
 */
function db_ready()
{
    require_once __DIR__ . "/database.db_ready.php";

    return database_db_ready_Class::getInstance();
}


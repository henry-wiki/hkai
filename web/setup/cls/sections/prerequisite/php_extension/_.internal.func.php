<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup prerequisite php_extension internal 短名（_.internal.func.php·php_extension 域）：同 namespace 直接叫（php_extension()——免 FQN）
// == 委派：prerequisite_php_extension_Class 單例（php_extension 域本體——php_extension 家族單元短名門）
// == 載入：本檔自載域本體（同層 _.bridge.cls.php——include once）＋經 sections/prerequisite/prerequisite.php require 鏈載入（本程式無 autoloader——顯式載入、零註冊）

// 載入域本體（_.bridge.cls.php 同層——prerequisite_php_extension_Class；include once）
require_once __DIR__ . "/_.bridge.cls.php";

/**
 * php_extension 短名（php_extension 域本體門——php_extension 家族單元短名入口；例：php_extension()->curl()->setup_check()）
 *
 * @return \sc\setup\sections\prerequisite\php_extension\prerequisite_php_extension_Class
 */
function php_extension()
{
    return \sc\setup\sections\prerequisite\php_extension\prerequisite_php_extension_Class::getInstance();
}

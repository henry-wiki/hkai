<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup prerequisite basic internal 短名（_.internal.func.php·basic 域）：同 namespace 直接叫（basic()——免 FQN）
// == 委派：prerequisite_basic_Class 單例（basic 域本體——basic 家族單元短名門）
// == 載入：本檔自載域本體（同層 _.bridge.cls.php——include once）＋經 sections/prerequisite/prerequisite.php require 鏈載入（本程式無 autoloader——顯式載入、零註冊）

// 載入域本體（_.bridge.cls.php 同層——prerequisite_basic_Class；include once）
require_once __DIR__ . "/_.bridge.cls.php";

/**
 * basic 短名（basic 域本體門——basic 家族單元短名入口；例：basic()->os_version()->setup_check()）
 *
 * @return \sc\setup\sections\prerequisite\basic\prerequisite_basic_Class
 */
function basic()
{
    return \sc\setup\sections\prerequisite\basic\prerequisite_basic_Class::getInstance();
}

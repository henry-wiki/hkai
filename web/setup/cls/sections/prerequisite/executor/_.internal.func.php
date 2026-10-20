<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup prerequisite executor internal 短名（_.internal.func.php·executor 域）：同 namespace 直接叫（executor()——免 FQN）
// == 委派：prerequisite_executor_Class 單例（executor 域本體——executor 家族單元短名門）
// == 載入：本檔自載域本體（同層 _.bridge.cls.php——include once）＋經 sections/prerequisite/prerequisite.php require 鏈載入（本程式無 autoloader——顯式載入、零註冊）

// 載入域本體（_.bridge.cls.php 同層——prerequisite_executor_Class；include once）
require_once __DIR__ . "/_.bridge.cls.php";

/**
 * executor 短名（executor 域本體門——executor 家族單元短名入口；例：executor()->pip()->setup_check()）
 *
 * @return \sc\setup\sections\prerequisite\executor\prerequisite_executor_Class
 */
function executor()
{
    return \sc\setup\sections\prerequisite\executor\prerequisite_executor_Class::getInstance();
}

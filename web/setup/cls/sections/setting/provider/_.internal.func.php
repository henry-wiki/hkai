<?php

namespace sc\setup\sections\setting;

// == HKAI setup setting provider internal 短名（_.internal.func.php·provider 域）：同 namespace 直接叫（provider()——免 FQN）
// == 委派：setting_provider_Class 單例（provider 域本體——provider 家族單元短名門）
// == 載入：本檔自載域本體（同層 _.bridge.cls.php——include once）＋經 sections/setting/setting.php require 鏈載入（本程式無 autoloader——顯式載入、零註冊）

// 載入域本體（_.bridge.cls.php 同層——setting_provider_Class；include once）
require_once __DIR__ . "/_.bridge.cls.php";

/**
 * provider 短名（provider 域本體門——provider 家族單元短名入口；例：provider()->openrouter()->setup_check()）
 *
 * @return \sc\setup\sections\setting\provider\setting_provider_Class
 */
function provider()
{
    return \sc\setup\sections\setting\provider\setting_provider_Class::getInstance();
}

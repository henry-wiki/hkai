<?php

namespace sc\setup\sections\setting;

// == HKAI setup setting benchmark internal 短名（_.internal.func.php·benchmark 域）：同 namespace 直接叫（benchmark()——免 FQN）
// == 委派：setting_benchmark_Class 單例（benchmark 域本體——benchmark 家族單元短名門）
// == 載入：本檔自載域本體（同層 _.bridge.cls.php——include once）＋經 sections/setting/setting.php require 鏈載入（本程式無 autoloader——顯式載入、零註冊）

// 載入域本體（_.bridge.cls.php 同層——setting_benchmark_Class；include once）
require_once __DIR__ . "/_.bridge.cls.php";

/**
 * benchmark 短名（benchmark 域本體門——benchmark 家族單元短名入口；例：benchmark()->llm_stats()->setup_check()）
 *
 * @return \sc\setup\sections\setting\benchmark\setting_benchmark_Class
 */
function benchmark()
{
    return \sc\setup\sections\setting\benchmark\setting_benchmark_Class::getInstance();
}

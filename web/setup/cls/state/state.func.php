<?php

namespace sc\setup\state;

// == HKAI setup state internal 短名（state.func.php·state 域）：同 namespace 直接叫（setup()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：本檔自載域本體（同層 state.cls.php——include once）＋經 setup.cls.php require 鏈載入（本程式無 autoloader——顯式載入、零註冊）

// 載入域本體（state.cls.php 同層——state_Class；include once）
require_once __DIR__ . "/state.cls.php";

/**
 * setup 單例（向上接駁——state 域 → setup 根；例：setup()->get_version()）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

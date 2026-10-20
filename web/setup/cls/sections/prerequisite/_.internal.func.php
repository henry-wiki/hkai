<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup sections/prerequisite internal 短名（_.internal.func.php·prerequisite 格）：同 namespace 直接叫（setup()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：sections/prerequisite/prerequisite.php require 鏈（本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——prerequisite 格 → setup 根；例：setup()->state()->get_setting_setup_process(...)）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

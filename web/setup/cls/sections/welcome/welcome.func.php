<?php

namespace sc\setup\sections\welcome;

// == HKAI setup sections/welcome internal 短名（welcome.func.php·welcome 格）：同 namespace 直接叫（setup()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：域 accessor（setup()->sections()->welcome()）隨載（零用家——對齊結構）

/**
 * setup 單例（向上接駁——welcome 格 → setup 根；例：setup()->state()->get_setting_setup_process(...)）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

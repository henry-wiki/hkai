<?php

namespace sc\setup\i18n;

// == HKAI setup i18n internal 短名（i18n.func.php·i18n 域）：同 namespace 直接叫（setup()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：域 accessor（setup()->i18n()）隨載（本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——i18n 域 → setup 根；例：setup()->state()->get_setting_setup_process(...)）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

<?php

namespace sc\setup\view;

// == HKAI setup view internal 短名（view.func.php·view 域）：同 namespace 直接叫（setup()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：setup 根 accessor（setup.cls.php view()——cls＋func 一對；本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——view 域 → setup 根；例：setup()->view()->render_setup_check(...)）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

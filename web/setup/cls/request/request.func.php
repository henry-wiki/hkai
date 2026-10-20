<?php

namespace sc\setup\request;

// == HKAI setup request internal 短名（request.func.php·request 域）：同 namespace 直接叫（setup()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：setup 根 accessor（setup.cls.php request()——cls＋func 一對；本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——request 域 → setup 根；例：setup()->sections()）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

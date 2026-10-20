<?php

namespace sc\setup\encrypter;

// == HKAI setup encrypter internal 短名（encrypter.func.php·encrypter 域）：同 namespace 直接叫（setup()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：域 accessor（setup()->encrypter()）隨載（本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——encrypter 域 → setup 根；例：setup()->get_version()）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

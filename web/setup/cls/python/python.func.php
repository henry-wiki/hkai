<?php

namespace sc\setup\python;

// == HKAI setup python internal 短名（python.func.php·python 域）：同 namespace 直接叫（setup()／python()——免 FQN）
// == 委派：setup_Class／python_Class 單例（class 引用集中本檔）
// == 載入：setup 根 accessor（setup.cls.php python()——cls＋func 一對；本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——python 域 → setup 根；例：setup()->is_exec_available()）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

/**
 * python 單例（本域 class——Python 相關操作；例：setup()->python()->detect_python_command()）
 *
 * @return \sc\setup\python\python_Class
 */
function python()
{
    return \sc\setup\python\python_Class::getInstance();
}

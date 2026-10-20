<?php

namespace sc\setup\sections\setting;

// == HKAI setup sections/setting internal 短名（setting.func.php·setting 格）：同 namespace 直接叫（setup()／timezone()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：setting 格本體（setting.php）頂載（本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——setting 格 → setup 根；例：setup()->state()->get_setting_setup_process(...)）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

/**
 * 檢查單元：timezone（按需 require 單元檔——同 namespace 直取）
 *
 * @return \sc\setup\sections\setting\setting_timezone_Class
 */
function timezone()
{
    require_once __DIR__ . "/setting.timezone.php";

    return setting_timezone_Class::getInstance();
}

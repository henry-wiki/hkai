<?php

namespace sc\setup\sections\install;

// == HKAI setup sections/install internal 短名（install.func.php·install 格）：同 namespace 直接叫（setup()／install_marker()——免 FQN）
// == 委派：setup_Class 單例（向上接駁——class 引用集中本檔）
// == 載入：install 格本體（install.php）頂載（本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——install 格 → setup 根；例：setup()->sections()->install()）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

/**
 * 檢查單元：install_marker（按需 require 單元檔——單例）
 *
 * @return \sc\setup\sections\install_marker_Class
 */
function install_marker()
{
    require_once __DIR__ . "/install.marker.php";

    return \sc\setup\sections\install_marker_Class::getInstance();
}

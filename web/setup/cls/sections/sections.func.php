<?php

namespace sc\setup\sections;

// == HKAI setup sections internal 短名（sections.func.php·sections 域）：setup() 本檔定義（六格共用——唔隨 install 鏈）
// == 委派：setup 根（setup() 單例門——方法直接用；i18n 經 setup()->i18n()）
// == 載入：經 cls/setup.func.php require 鏈＋setup()->sections() accessor（include once；本程式無 autoloader——顯式載入、零註冊）

/**
 * setup 單例（向上接駁——sections 域 → setup 根；例：setup()->sections()->get_sections()）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

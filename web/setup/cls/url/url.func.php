<?php

namespace sc\setup\url;

// == HKAI setup url internal 短名（url.func.php·url 域）：同 namespace 直接叫（url()——免 FQN）
// == 委派：url_Class 單例（本域接駁——class 引用集中本檔）
// == 載入：本檔自載域本體（同層 url.cls.php——include once）＋經 setup.func.php require 鏈載入（本程式無 autoloader——顯式載入、零註冊）

// 載入域本體（url.cls.php 同層——url_Class；include once）
require_once __DIR__ . "/url.cls.php";

/**
 * url 單例（本域 class——請求網址段解析；例：url()->get_segment(0) ＝首段）
 *
 * @return \sc\setup\url\url_Class
 */
function url()
{
    return \sc\setup\url\url_Class::getInstance();
}

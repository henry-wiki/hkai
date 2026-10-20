<?php

namespace sc\setup;

// == HKAI setup bridging：setup.func.php（cls 根——對外 API 全部委派 setup_Class 單例）
// == 規則：本檔零實作——每函數＝一線委派；邏輯本體一律喺 cls/setup.cls.php
// == 載入：核心 class（setup.cls.php——唯一預載）；其餘各域 cls＋func 由 setup()->xxx() accessor 一對按需載（子動作 trait 由各域 cls 自載）
// == 規格：零框架（唔用 autoloader／session／DB——只用 PHP 內建）；全檔 namespace sc\setup；語法下限 PHP 5.4

// ---------------------------------------------------------
// 載入核心 class（其餘各域 cls＋func 由 setup()->xxx() accessor 一對按需載——唔預載）
// ---------------------------------------------------------
require_once __DIR__ . "/setup.cls.php";

// ---------------------------------------------------------
// 域內短名（同 namespace 直接叫：setup()）
// ---------------------------------------------------------

/**
 * setup 根單例（程式根短名——例：setup()->view()->load_setup_page()）
 *
 * @return \sc\setup\setup_Class
 */
function setup()
{
    return \sc\setup\setup_Class::getInstance();
}

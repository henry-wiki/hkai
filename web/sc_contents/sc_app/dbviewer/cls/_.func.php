<?php

// == sc_app/dbviewer/cls/_.func.php：dbviewer app 層 bridging（由 load_app_context ① 自動載入——cls/ 內 func 檔） ==
// == class 檔由 autoload 惰性載入（namespace sc\app\dbviewer\cls → dbviewer_core.cls.php / dbviewer_main.cls.php） ==

use sc\app\dbviewer\cls;

/**
 * @return cls\dbviewer_core_Class
 */
function dbviewer__core()
{
    return cls\dbviewer_core_Class::getInstance();
}

/**
 * @return cls\dbviewer_main_Class
 */
function dbviewer__main()
{
    return cls\dbviewer_main_Class::getInstance();
}

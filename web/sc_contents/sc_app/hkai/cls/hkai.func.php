<?php

// == sc_app/hkai/cls/hkai.func.php：hkai app 層 bridging（由 load_app_context ①b 自動載入——cls/ 內 func 檔） ==
// == class 檔由 autoload 惰性載入（namespace sc\app\hkai\cls → hkai.php） ==

use sc\app\hkai\cls;

/**
 * @return cls\hkai_Class
 */
function hkai()
{
    return cls\hkai_Class::getInstance();
}

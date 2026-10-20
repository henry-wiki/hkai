<?php

// == sc_app/hkai/cls/sc_runtime/_.func.php：sc_runtime 域 bridging ==
// == 載入：framework boot 的 load_func(SC_DIR_APP) 遞迴掃描（sc_boot_load_framework.cls.php:26） ==
// == class 經 autoload 動態載入（namespace sc\app\hkai\cls\sc_runtime → sc_runtime.cls.php） ==

use sc\app\hkai\cls;

/**
 * @return cls\sc_runtime\sc_runtime_Class
 */
function hkai__sc_runtime()
{
    return cls\sc_runtime\sc_runtime_Class::getInstance();
}

/**
 * @return cls\sc_runtime\sc_runtime_io_Class
 */
function hkai__sc_runtime_io()
{
    return cls\sc_runtime\sc_runtime_io_Class::getInstance();
}

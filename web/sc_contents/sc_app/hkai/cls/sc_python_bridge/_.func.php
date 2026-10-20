<?php

// == sc_app/hkai/cls/sc_python_bridge/_.func.php：sc_python_bridge 域 bridging ==
// == 載入：framework boot 的 load_func(SC_DIR_APP) 遞迴掃描（sc_boot_load_framework.cls.php:26） ==
// == class 經 autoload 動態載入（namespace sc\app\hkai\cls\sc_python_bridge → sc_python_bridge.cls.php） ==

use sc\app\hkai\cls;

/**
 * @return cls\sc_python_bridge\sc_python_bridge_Class
 */
function hkai__python_bridge()
{
    return cls\sc_python_bridge\sc_python_bridge_Class::getInstance();
}

/**
 * @return cls\sc_python_bridge\sc_python_bridge_check_Class
 */
function hkai__python_bridge_check()
{
    return cls\sc_python_bridge\sc_python_bridge_check_Class::getInstance();
}

/**
 * @return cls\sc_python_bridge\sc_python_bridge_process_Class
 */
function hkai__python_bridge_process()
{
    return cls\sc_python_bridge\sc_python_bridge_process_Class::getInstance();
}

/**
 * @return cls\sc_python_bridge\sc_python_bridge_daemon_Class
 */
function hkai__python_bridge_daemon()
{
    return cls\sc_python_bridge\sc_python_bridge_daemon_Class::getInstance();
}

/**
 * @return cls\sc_python_bridge\sc_python_launcher_Class
 */
function hkai__python_launcher()
{
    return cls\sc_python_bridge\sc_python_launcher_Class::getInstance();
}

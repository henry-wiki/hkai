<?php

// == sc_app/hkai/cls/_.func.php：hkai app 層 bridging（由 load_app_context ①b 自動載入——cls/ 內 func 檔） ==
// == class 檔由 autoload 惰性載入（namespace sc\app\hkai\cls → hkai_main.cls.php） ==

use sc\app\hkai\cls;

/**
 * @return cls\hkai_main_Class
 */
function hkai__main()
{
    return cls\hkai_main_Class::getInstance();
}

/**
 * @return cls\hkai_core_Class
 */
function hkai__core()
{
    return cls\hkai_core_Class::getInstance();
}

/**
 * @return cls\hkai_daemon_secure_Class
 */
function hkai__daemon_secure()
{
    return cls\hkai_daemon_secure_Class::getInstance();
}

/**
 * @return cls\i18n\main_Class
 */
function hkai__i18n()
{
    return cls\i18n\main_Class::getInstance();
}

/**
 * @return cls\sc_page_builder\sc_page_builder_view_Class
 */
function hkai__sc_page_builder_view()
{
    return cls\sc_page_builder\sc_page_builder_view_Class::getInstance();
}

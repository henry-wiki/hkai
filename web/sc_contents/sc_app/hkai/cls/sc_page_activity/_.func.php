<?php

// == sc_app/hkai/cls/sc_page_activity/_.func.php：sc_page_activity 域 bridging ==
// == 載入：framework boot 的 load_func(SC_DIR_APP) 遞迴掃描（sc_boot_load_framework.cls.php:26） ==
// == class 經 autoload 動態載入（namespace sc\app\hkai\cls\sc_page_activity → sc_page_activity.cls.php） ==

use sc\app\hkai\cls;

/**
 * @return \sc\app\hkai\cls\sc_page_activity\sc_page_activity_Class
 */
function hkai__sc_page_activity()
{
    return cls\sc_page_activity\sc_page_activity_Class::getInstance();
}

/**
 * @return \sc\app\hkai\cls\sc_page_activity\repo_update\repo_update_Class
 */
function hkai__sc_page_activity_repo_update()
{
    return cls\sc_page_activity\repo_update\repo_update_Class::getInstance();
}

/**
 * @return \sc\app\hkai\cls\sc_page_activity\repo_select\repo_select_Class
 */
function hkai__sc_page_activity_repo_select()
{
    return cls\sc_page_activity\repo_select\repo_select_Class::getInstance();
}

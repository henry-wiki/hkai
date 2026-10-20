<?php

// == sc_app/hkai/cls/sc_oauth_keys/_.func.php：sc_oauth_keys 域 bridging ==
// == 載入：framework boot 的 load_func(SC_DIR_APP) 遞迴掃描（sc_boot_load_framework.cls.php:26） ==
// == class 經 autoload 動態載入（namespace sc\app\hkai\cls\sc_oauth_keys → sc_oauth_keys.cls.php） ==

use sc\app\hkai\cls;

/**
 * @return cls\sc_oauth_keys\sc_oauth_keys_Class
 */
function hkai__sc_oauth_keys()
{
    return cls\sc_oauth_keys\sc_oauth_keys_Class::getInstance();
}

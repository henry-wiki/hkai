<?php

use sc\app\hkai\plugins\sc_ai_status_bar;

/**
 * 插件頂層單例（sc_ai_status_bar_Class——域入口：sc_ai_status_bar()->status_bar()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_status_bar\sc_ai_status_bar_Class
 */
function sc_ai_status_bar()
{
    return sc_ai_status_bar\sc_ai_status_bar_Class::getInstance();
}

<?php

use sc\app\hkai\plugins\sc_ai_health;

/**
 * 插件頂層單例（sc_ai_health_Class——域入口：sc_ai_health()->health()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_health\sc_ai_health_Class
 */
function sc_ai_health()
{
    return sc_ai_health\sc_ai_health_Class::getInstance();
}

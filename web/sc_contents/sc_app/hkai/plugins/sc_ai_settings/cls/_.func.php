<?php

use sc\app\hkai\plugins\sc_ai_settings;

/**
 * 插件頂層單例（sc_ai_settings_Class——域入口：sc_ai_settings()->server()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_settings\sc_ai_settings_Class
 */
function sc_ai_settings()
{
    return sc_ai_settings\sc_ai_settings_Class::getInstance();
}

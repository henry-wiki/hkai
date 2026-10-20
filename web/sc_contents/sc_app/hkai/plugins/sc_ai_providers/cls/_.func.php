<?php

use sc\app\hkai\plugins\sc_ai_providers;

/**
 * 插件頂層單例（sc_ai_providers_Class——域入口：sc_ai_providers()->main()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_providers\sc_ai_providers_Class
 */
function sc_ai_providers()
{
    return sc_ai_providers\sc_ai_providers_Class::getInstance();
}

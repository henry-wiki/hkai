<?php

use sc\app\hkai\plugins\sc_ai_diagnostics;

/**
 * 插件頂層單例（sc_ai_diagnostics_Class——域入口：sc_ai_diagnostics()->git()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_diagnostics\sc_ai_diagnostics_Class
 */
function sc_ai_diagnostics()
{
    return sc_ai_diagnostics\sc_ai_diagnostics_Class::getInstance();
}

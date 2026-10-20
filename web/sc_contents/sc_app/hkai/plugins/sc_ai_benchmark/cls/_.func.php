<?php

use sc\app\hkai\plugins\sc_ai_benchmark;

/**
 * 插件頂層單例（sc_ai_benchmark_Class——域入口：sc_ai_benchmark()->main()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_benchmark\sc_ai_benchmark_Class
 */
function sc_ai_benchmark()
{
    return sc_ai_benchmark\sc_ai_benchmark_Class::getInstance();
}

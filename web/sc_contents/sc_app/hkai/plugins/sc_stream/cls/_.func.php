<?php

use sc\app\hkai\plugins\sc_stream;

/**
 * 插件頂層單例（sc_stream_Class——域入口：sc_stream()->sse_state()->run_state_stream()）
 *
 * @return \sc\app\hkai\plugins\sc_stream\sc_stream_Class
 */
function sc_stream()
{
    return sc_stream\sc_stream_Class::getInstance();
}

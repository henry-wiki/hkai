<?php

use sc\app\hkai\plugins\sc_ai_chats;

/**
 * 插件頂層單例（sc_ai_chats_Class——域入口：sc_ai_chats()->queue()->…）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\sc_ai_chats_Class
 */
function sc_ai_chats()
{
    return sc_ai_chats\sc_ai_chats_Class::getInstance();
}

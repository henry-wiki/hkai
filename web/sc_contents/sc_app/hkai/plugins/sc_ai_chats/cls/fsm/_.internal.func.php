<?php

namespace sc\app\hkai\plugins\sc_ai_chats\fsm;

// == sc_ai_chats fsm 域 internal 短名：同 namespace 直接叫（fsm()——免 use function） ==

/**
 * fsm 單例（本域短名——例：fsm()->resolve_status_by_event($event)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\fsm\fsm_Class
 */
function fsm()
{
    return sc_ai_chats()->fsm();
}

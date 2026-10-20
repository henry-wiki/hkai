<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll;

// == sc_ai_chats poll 域 internal 短名（_.internal.func.php·子域版）：同 namespace 直接叫（poll()——免 use function） ==
// == 委派全局 bridging（解析源單一——class 引用仍只喺插件 cls/_.func.php） ==

/**
 * poll 單例（本域短名——例：poll()->file()->get_poll_jobs($chat_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\poll\poll_Class
 */
function poll()
{
    return sc_ai_chats()->poll();
}

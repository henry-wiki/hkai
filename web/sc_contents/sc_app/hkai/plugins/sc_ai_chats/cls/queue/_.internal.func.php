<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue;

// == sc_ai_chats queue 域 internal 短名（_.internal.func.php·子域版）：同 namespace 直接叫（queue()——免 use function） ==
// == 委派全局 bridging（解析源單一——class 引用仍只喺插件 cls/_.func.php） ==

/**
 * queue 單例（本域短名——例：queue()->get_queue_lines($chat_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\queue\queue_Class
 */
function queue()
{
    return sc_ai_chats()->queue();
}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message;

// == sc_ai_chats message 域 internal 短名：同 namespace 直接叫（message()——免 use function） ==
// == 2026-09-27 域收攏：八個單元短名（view／init／repo_select…）併成 message()；prompt() 跨域保留 ==

/**
 * message 單例（本域短名——例：message()->repo_select()->get_message_row($message_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\message\message_Class
 */
function message()
{
    return sc_ai_chats()->message();
}

/**
 * prompt 單例（跨域短名——例：prompt()->render_queue_list($chat_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\prompt\prompt_Class
 */
function prompt()
{
    return sc_ai_chats()->prompt();
}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\prompt;

// == sc_ai_chats prompt 域 internal 短名：同 namespace 直接叫（prompt()——免 use function） ==
// == 2026-09-27 收攏：repo_select()／init() 死委派撤（已併入 sc_ai_chats()->chat()——短名冇人用） ==

/**
 * prompt 單例（本域短名——例：prompt()->shift_queue_message($chat_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\prompt\prompt_Class
 */
function prompt()
{
    return sc_ai_chats()->prompt();
}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat;

// == sc_ai_chats chat 域 internal 短名：同 namespace 直接叫（chat()——免 use function） ==
// == 2026-09-27 域收攏：init／repo_select 短名併成 chat() ==

/**
 * chat 單例（本域短名——例：chat()->get_chat_row_by_sid($chat_sid)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\chat\chat_Class
 */
function chat()
{
    return sc_ai_chats()->chat();
}

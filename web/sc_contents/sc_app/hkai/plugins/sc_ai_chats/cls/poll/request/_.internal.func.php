<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\request;

// == sc_ai_chats poll\request 子域 internal 短名：同 namespace 直接叫（request()——免 use function） ==
// == 接駁：委派 sc_ai_chats()->poll()->request()（唯一入口＝poll()->request()） ==

/**
 * request 單例（本子域短名——例：request()->handle_message_poll()）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\poll\request\request_Class
 */
function request()
{
    return sc_ai_chats()->poll()->request();
}

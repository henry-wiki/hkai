<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\log;

// == sc_ai_chats poll\log 子域 internal 短名（只放 function）：同 namespace 直接叫（log()——免 use function） ==
// == 接駁：委派 sc_ai_chats()->poll()->log()（唯一入口） ==

/**
 * log 單例（本子域短名——例：log()->append_log(…)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\poll\log\log_Class
 */
function log()
{
    return sc_ai_chats()->poll()->log();
}

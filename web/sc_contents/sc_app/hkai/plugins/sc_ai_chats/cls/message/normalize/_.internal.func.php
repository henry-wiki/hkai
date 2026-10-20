<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\normalize;

// == sc_ai_chats message\normalize 子域 internal 短名（只放 function）：同 namespace 直接叫（normalize()——免 use function） ==
// == 接駁：委派 sc_ai_chats()->message()->normalize()（唯一入口） ==

/**
 * normalize 單例（本子域短名——例：normalize()->response_brief_rows(…)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_chats\message\normalize\normalize_Class
 */
function normalize()
{
    return sc_ai_chats()->message()->normalize();
}

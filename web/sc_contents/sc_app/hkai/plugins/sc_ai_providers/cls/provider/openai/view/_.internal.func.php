<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openai\view;

// == sc_ai_providers provider/openai view：internal 短名（只放 function）——同 namespace 直接叫（provider()——免 use function） ==
// == 接駁：委派 sc_ai_providers()->instance__openai()（唯一入口）——包內唔硬編 provider 名（複製新 provider 零改名） ==

/**
 * provider 單例（本目錄短名——例：provider()->view()->get_model_list_page($provider_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_providers\provider\openai\provider_openai_Class
 */
function provider()
{
    return sc_ai_providers()->instance__openai();
}

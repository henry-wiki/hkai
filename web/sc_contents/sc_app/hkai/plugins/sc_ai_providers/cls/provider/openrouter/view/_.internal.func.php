<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\view;

// == sc_ai_providers provider/openrouter view：internal 短名（只放 function）——同 namespace 直接叫（provider()——免 use function） ==
// == 接駁：委派 sc_ai_providers()->instance__openrouter()（唯一入口）——包內唔硬編 provider 名（複製新 provider 零改名） ==

/**
 * provider 單例（本目錄短名——例：provider()->view()->get_model_list_page($provider_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\provider_openrouter_Class
 */
function provider()
{
    return sc_ai_providers()->instance__openrouter();
}

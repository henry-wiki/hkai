<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\hermes_agent\repo;

// == sc_ai_providers provider/hermes_agent repo：internal 短名（只放 function）——同 namespace 直接叫（provider()——免 use function） ==
// == 接駁：委派 sc_ai_providers()->instance__hermes_agent()（唯一入口）——包內唔硬編 provider 名（複製新 provider 零改名） ==

/**
 * provider 單例（本目錄短名——例：provider()->repo()->get_provider_model_data()）
 *
 * @return \sc\app\hkai\plugins\sc_ai_providers\provider\hermes_agent\provider_hermes_agent_Class
 */
function provider()
{
    return sc_ai_providers()->instance__hermes_agent();
}

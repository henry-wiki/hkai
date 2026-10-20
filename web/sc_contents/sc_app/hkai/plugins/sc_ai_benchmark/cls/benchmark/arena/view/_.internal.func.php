<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\benchmark\arena\view;

// == sc_ai_benchmark benchmark/arena view：internal 短名（只放 function）——同 namespace 直接叫（bench_source()——免 use function） ==
// == 接駁：委派 sc_ai_benchmark()->instance__arena()（唯一入口）——包內唔硬編 bench_source 名（複製新 bench_source 零改名） ==

/**
 * bench_source 單例（本目錄短名——例：bench_source()->view()->get_model_list_page($bench_source_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_benchmark\benchmark\arena\bench_source_arena_Class
 */
function bench_source()
{
    return sc_ai_benchmark()->instance__arena();
}

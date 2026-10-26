<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == timezone_Class：時區一行（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class timezone_Class
{
    // Timezone（必出）
    public static function render($env_data)
    {
        return ["- Timezone: " . ($env_data["timezone"] ?? "")];
    }
}

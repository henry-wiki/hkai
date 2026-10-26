<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == container_Class：容器一行（只有 true 才出）（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class container_Class
{
    // 容器（只有 container === true 才出）
    public static function render($env_data)
    {
        // 判定：唔係容器 → 唔出
        if (($env_data["container"] ?? false) !== true) {
            return [];
        }

        return ["- Container: running inside a container"];
    }
}

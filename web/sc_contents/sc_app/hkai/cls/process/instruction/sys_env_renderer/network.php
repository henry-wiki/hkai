<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == network_Class：網絡一行（二選一）（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class network_Class
{
    // 網絡（有／無外網——二選一）
    public static function render($env_data)
    {
        $network = $env_data["network"] ?? [];

        // 判定：有外網
        if (($network["internet"] ?? false) === true) {
            return ["- Network: internet reachable"];
        }

        return ["- Network: no internet"];
    }
}

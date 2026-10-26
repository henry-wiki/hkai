<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == os_Class：OS／Hostname 兩行（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class os_Class
{
    // OS／Hostname（platform 欄位——必出）
    public static function render($env_data)
    {
        $os_info = $env_data["os"] ?? [];

        $os_lines   = [];
        $os_lines[] = "- OS: " . ($os_info["name"] ?? "") . " " . ($os_info["release"] ?? "") . " (" . ($os_info["arch"] ?? "") . ")";
        $os_lines[] = "- Hostname: " . ($os_info["hostname"] ?? "");

        return $os_lines;
    }
}

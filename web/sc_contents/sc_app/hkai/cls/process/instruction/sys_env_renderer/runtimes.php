<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == runtimes_Class：runtimes 一行（缺＝空）（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class runtimes_Class
{
    // runtimes（名稱 版本——逗號接；缺＝空）
    public static function render($env_data)
    {
        $runtimes = $env_data["runtimes"] ?? [];

        // 判定：冇 runtime → 唔出
        if (count($runtimes) === 0) {
            return [];
        }

        $runtime_texts = [];

        // 逐 runtime 組「名稱 版本」
        foreach ($runtimes as $runtime_name => $runtime_version) {
            $runtime_texts[] = $runtime_name . " " . $runtime_version;
        }

        return ["- Runtimes: " . implode(", ", $runtime_texts)];
    }
}

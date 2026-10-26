<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == hardware_Class：CPU 核心／記憶體兩行（缺＝空）（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class hardware_Class
{
    // CPU 核心／記憶體（缺＝空 array → 唔出；GB 一位小數）
    public static function render($env_data)
    {
        $hardware = $env_data["hardware"] ?? [];

        // 判定：冇硬件資料 → 唔出
        if (count($hardware) === 0) {
            return [];
        }

        $memory_total_gb     = number_format((float)($hardware["memory_total_gb"] ?? 0), 1, ".", "");
        $memory_available_gb = number_format((float)($hardware["memory_available_gb"] ?? 0), 1, ".", "");

        $hardware_lines   = [];
        $hardware_lines[] = "- CPU cores: " . ($hardware["cpu_cores"] ?? "");
        $hardware_lines[] = "- Memory: " . $memory_total_gb . " GB total (" . $memory_available_gb . " GB available)";

        return $hardware_lines;
    }
}

<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == disk_Class：工作碟一行（缺＝空）（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class disk_Class
{
    // 工作碟（失敗＝null → 唔出；GB 一位小數）
    public static function render($env_data)
    {
        $disk = $env_data["disk"] ?? null;

        // 判定：冇磁碟資料 → 唔出
        if ($disk === null) {
            return [];
        }

        $free_gb_text  = number_format((float)($disk["free_gb"] ?? 0), 1, ".", "");
        $total_gb_text = number_format((float)($disk["total_gb"] ?? 0), 1, ".", "");

        $disk_lines   = [];
        $disk_lines[] = "- Disk (" . ($disk["mount"] ?? "") . "): " . $free_gb_text . " GB free / " . $total_gb_text . " GB";

        return $disk_lines;
    }
}

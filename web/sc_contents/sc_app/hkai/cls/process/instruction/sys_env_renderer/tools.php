<?php

namespace sc\app\hkai\cls\process\instruction\sys_env_renderer;

// == tools_Class：tools 分類行（缺＝空）（sys_env 單段 renderer——一檔一段） ==
// == 上游：instruction_sys_env_Class::collect_sys_env_lines()（按 CONTENT_TEMPLATE 分派） ==
// == 下游：bridge JSON（$env_data）→ 回傳行 array ==

class tools_Class
{
    // tools（逐分類一行；底線轉空格；缺＝空）
    public static function render($env_data)
    {
        $tools = $env_data["tools"] ?? [];

        // 判定：冇工具資料 → 唔出
        if (count($tools) === 0) {
            return [];
        }

        $tools_lines = [];

        // 逐分類組「分類: a, b, c」
        foreach ($tools as $tool_category => $tool_names) {
            $tool_label    = str_replace("_", " ", $tool_category);
            $tools_lines[] = "- " . $tool_label . ": " . implode(", ", $tool_names);
        }

        return $tools_lines;
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_benchmark\repo\repo_update;

// == sc_ai_benchmark repo php_code_export：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_benchmark_main 分遷入（one file one action） ==
// == 2026-10-06 自 main 域分遷入 repo（one file one action） ==

trait php_code_export
{
    // PHP 陣列 → 可寫入 PHP 檔的字串（遞迴 pretty-print：[] 語法、2 空格縮排、list 省略 key、assoc 保留 key、尾逗號）
    public function php_code_export($data, $indent_level = 0)
    {
        // 當前層縮排（每層 2 空格）
        $spaces = str_repeat("  ", $indent_level);
        
        if (is_array($data)) {
            // 空陣列 → []
            if (empty($data)) {
                return "[]";
            }
            
            $is_list = $this->is_list_array($data);
            $items = [];
            
            // 逐元素匯出（list 省略 key；assoc 保留 key =>）
            foreach ($data as $array_key => $array_item) {
                $formatted_value = $this->php_code_export($array_item, $indent_level + 1);
                if ($is_list) {
                    $items[] = str_repeat("  ", $indent_level + 1) . $formatted_value;
                } else {
                    $formatted_key = var_export($array_key, true);
                    $items[] = str_repeat("  ", $indent_level + 1) . $formatted_key . " => " . $formatted_value;
                }
            }
            
            return "[\n" . implode(",\n", $items) . ",\n" . $spaces . "]";
        }
        
        return var_export($data, true);
    }
}

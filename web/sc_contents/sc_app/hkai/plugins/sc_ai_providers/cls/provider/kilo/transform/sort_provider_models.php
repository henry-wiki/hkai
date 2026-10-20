<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\kilo\transform;

// == sc_ai_providers provider/kilo transform sort_provider_models：域動作（trait——掛入 provider_kilo_Class；檔＝動作） ==
// == 2026-10-06 自 provider/kilo/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait sort_provider_models
{
    // 依多欄排序規格排序（先解析一次成結構——compare 時逐欄查表；欄位缺失視 null 恆排後；全欄相等以 id 兜底）
    private function sort_provider_models(&$model_list)
    {
        // 解析 sort_by（逐欄拆 field + direction——缺省 asc）
        if(!provider()->provider_model_sort_by)
        {
            return ;
        }
        
        $sort_fields = [];
        foreach (provider()->provider_model_sort_by as $sort_spec_item) {
            $sort_parts     = explode(" ", trim($sort_spec_item));
            $sort_field     = $sort_parts[0];
            $sort_direction = isset($sort_parts[1]) ? strtolower($sort_parts[1]) : "asc";
            $sort_fields[]  = ["field" => $sort_field, "is_desc" => ($sort_direction === "desc")];
        }
        
        // 多欄依序比較（逐欄：null 規則 → 數值/字串分流 → desc 反轉）
        uksort($model_list, function ($model_id_a, $model_id_b) use ($model_list, $sort_fields) {
            foreach ($sort_fields as $sort_field_item) {
                $sort_field = $sort_field_item["field"];
                $value_a = isset($model_list[$model_id_a][$sort_field]) ? $model_list[$model_id_a][$sort_field] : null;
                $value_b = isset($model_list[$model_id_b][$sort_field]) ? $model_list[$model_id_b][$sort_field] : null;
                
                // 一方 null → null 恆排後（與方向無關）
                if ($value_a === null && $value_b !== null) {
                    return 1;
                }
                if ($value_a !== null && $value_b === null) {
                    return -1;
                }
                if ($value_a === null && $value_b === null) {
                    continue;
                }
                
                // 雙方皆數值（含數值字串）→ 數值比較；否則字串自然排序（忽略大小寫——數字依數值排）
                if (is_numeric($value_a) && is_numeric($value_b)) {
                    $compare_result = ($value_a == $value_b) ? 0 : (($value_a < $value_b) ? -1 : 1);
                } else {
                    $compare_result = strnatcasecmp((string)$value_a, (string)$value_b);
                }
                
                // 該欄有差異 → 依方向回傳（desc 反轉）
                if ($compare_result !== 0) {
                    return $sort_field_item["is_desc"] ? -$compare_result : $compare_result;
                }
            }
            
            // 全欄相等 → 依 id 自然排序兜底（全表次序完全確定）
            return strnatcasecmp($model_id_a, $model_id_b);
        });
    }
}

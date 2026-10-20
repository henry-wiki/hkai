<?php

namespace sc\app\hkai\plugins\sc_ai_providers\repo\repo_update;

// == sc_ai_providers repo is_list_array：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-06 自 main_Class 分遷入 repo（one file one action） ==

trait is_list_array
{
    // 純數字陣列判斷（key 依 0,1,2... 連續——PHP 7.3 相容：無 typed property）
    private function is_list_array($array_value)
    {
        // 空陣列視為 list
        if ($array_value === []) {
            return true;
        }
        
        $expected_index = 0;
        foreach ($array_value as $array_key => $array_item) {
            // key 非連續數字 → 非 list
            if ($array_key !== $expected_index) {
                return false;
            }
            $expected_index++;
        }
        
        return true;
    }
}

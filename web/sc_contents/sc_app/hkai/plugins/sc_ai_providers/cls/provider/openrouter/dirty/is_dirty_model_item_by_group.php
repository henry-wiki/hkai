<?php

namespace sc\app\hkai\plugins\sc_ai_providers\provider\openrouter\dirty;

// == sc_ai_providers provider/openrouter dirty is_dirty_model_item_by_group：域動作（trait——掛入 provider_openrouter_Class；檔＝動作） ==
// == 2026-10-06 自 provider/openrouter/_.bridge.cls.php 分遷入（按函數工作內容分檔） ==

trait is_dirty_model_item_by_group
{
    private function is_dirty_model_item_by_group($model_item,&$dirty_reason = "")
    {
        $_is_dirty = false;
        
        $_model_id = $model_item["id"];
        
        // 提取組名（/ 前的字串）
        $_slash_pos = strpos($_model_id, "/");
        if ($_slash_pos === false) {
            return $_is_dirty;
        }
        $_group_name = substr($_model_id, 0, $_slash_pos);
        
        // 該組無 dirty 規則 → 跳過
        if (!isset(provider()->dirty_model_groups[$_group_name])) {
            return $_is_dirty;
        }
        
        // 遍歷該組的比對清單
        $_dirty_patterns = provider()->dirty_model_groups[$_group_name];
        foreach ($_dirty_patterns as $dirty_tag) {
            if (strpos($_model_id, $dirty_tag) !== false) {
                $_is_dirty = true;
                
                $dirty_reason = "match dirty tag : {$dirty_tag}";
                
                break;
            }
        }
        
        return $_is_dirty;
    }
}

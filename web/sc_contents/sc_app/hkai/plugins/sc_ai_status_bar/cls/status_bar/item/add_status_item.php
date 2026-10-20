<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\item;

// == sc_ai_status_bar main add_status_item：域動作（trait——掛入 main_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_status_bar_main 分遷入（one file one action） ==

trait add_status_item
{
    // 外部貢獻：加入 status item（region_key 如 "main/left"／"project"；item 只接受 array 或 Closure）
    // 用法：sc_ai_status_bar()->status_bar()->add_status_item("workspace/left", function () { return sc_ai_diagnostics__main()->get_status_item(); });
    // 語意：array＝靜態定義；Closure＝render 時求值（每次輸出取當下資料，如動態診斷值）
    public function add_status_item($region_key, $item)
    {
        // item 為 array → 直接收集（靜態定義）
        if (is_array($item)) {
            $this->status_items[$region_key][] = $item;
            
            return $this;
        }
        
        // item 為 Closure → 收集（render 時求值）
        if ($item instanceof \Closure) {
            $this->status_items[$region_key][] = $item;
            
            return $this;
        }
        
        // 型別不符（只接受 array／Closure）→ 拒絕
        return $this;
    }
}

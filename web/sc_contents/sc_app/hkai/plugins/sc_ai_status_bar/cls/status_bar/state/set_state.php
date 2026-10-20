<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\state;

// == sc_ai_status_bar main set_state：域動作（trait——掛入 main_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_status_bar_main 分遷入（one file one action） ==

trait set_state
{
    // 更新 item 狀態（狀態鍵版本——外部模組呼叫；依 item states 定義映射文字/class）
    // 用法：sc_ai_status_bar()->status_bar()->set_state("sb_conn", "connected")
    public function set_state($item_id, $state_key)
    {
        // item 未定義 → 不動
        if (!isset($this->item_states[$item_id])) {
            return;
        }
        
        // item 無 states 定義 → 不動
        if (!isset($this->item_configs[$item_id]["states"])) {
            return;
        }
        
        $item_states = $this->item_configs[$item_id]["states"];
        
        // 狀態鍵未定義 → 不動
        if (!isset($item_states[$state_key])) {
            return;
        }
        
        $state = $item_states[$state_key];
        
        $this->item_states[$item_id] = [
            "text"   => $state["text"] ?? "",
            "class"  => $state["class"] ?? "",
            "hidden" => $state["hidden"] ?? false,
        ];
    }
}

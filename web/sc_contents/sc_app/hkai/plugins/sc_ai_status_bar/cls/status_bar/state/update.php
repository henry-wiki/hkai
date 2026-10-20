<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\state;

// == sc_ai_status_bar main update：域動作（trait——掛入 main_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_status_bar_main 分遷入（one file one action） ==

trait update
{
    // 更新 item 狀態（外部模組呼叫）
    // 用法：sc_ai_status_bar()->status_bar()->update("sb_model", "gpt-5 · build")
    public function update($item_id, $text, $class = "", $hidden = false)
    {
        if (!isset($this->item_states[$item_id])) {
            return;
        }
        
        $this->item_states[$item_id] = [
            "text"   => $text,
            "class"  => $class,
            "hidden" => $hidden,
        ];
    }
}

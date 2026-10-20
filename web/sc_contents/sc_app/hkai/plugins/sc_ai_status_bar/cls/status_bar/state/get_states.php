<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\state;

// == sc_ai_status_bar main get_states：域動作（trait——掛入 main_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_status_bar_main 分遷入（one file one action） ==

trait get_states
{
    // 取得所有 item 狀態（供 JS 讀取）
    public function get_states()
    {
        return $this->item_states;
    }
}

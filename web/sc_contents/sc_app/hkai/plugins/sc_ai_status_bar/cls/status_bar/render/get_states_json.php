<?php

namespace sc\app\hkai\plugins\sc_ai_status_bar\status_bar\render;

// == sc_ai_status_bar main get_states_json：域動作（trait——掛入 main_Class；檔＝動作） ==
// == 2026-09-27 自舊 flat sc_ai_status_bar_main 分遷入（one file one action） ==

trait get_states_json
{
    // 取得 JSON（供 JS 讀取更新）
    public function get_states_json()
    {
        return json_encode($this->item_states, JSON_UNESCAPED_UNICODE);
    }
}

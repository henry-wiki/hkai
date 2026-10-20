<?php

namespace sc\app\hkai\plugins\sc_ai_chats\fsm;

// == sc_ai_chats fsm get_display_state：fsm 域查詢（trait——掛入 fsm_Class；檔＝動作） ==
// == 2026-09-27 自 cls/sc_ai_chats_fsm.php 分獨立檔（one file one function） ==

trait get_display_state
{

    // event → 顯示 state（活動列顏色分組；未列／未知＝""——前端沿用上一條）
    public function get_display_state($event)
    {
        return $this->event_to_display_state[$event] ?? "";
    }
}

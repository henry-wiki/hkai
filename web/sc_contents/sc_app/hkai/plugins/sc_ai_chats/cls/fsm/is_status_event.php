<?php

namespace sc\app\hkai\plugins\sc_ai_chats\fsm;

// == sc_ai_chats fsm is_status_event：fsm 域查詢（trait——掛入 fsm_Class；檔＝動作） ==
// == 2026-09-27 自 cls/sc_ai_chats_fsm.php 分獨立檔（one file one function） ==

trait is_status_event
{

    // 此事件是否會驅動 status（有轉移定義）
    public function is_status_event($event)
    {
        return isset($this->status_events[$event]);
    }
}

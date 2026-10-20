<?php

namespace sc\app\hkai\plugins\sc_ai_chats\fsm;

// == sc_ai_chats fsm resolve_status_by_event：fsm 域查詢（trait——掛入 fsm_Class；檔＝動作） ==
// == 2026-09-27 自 cls/sc_ai_chats_fsm.php 分獨立檔（one file one function） ==

trait resolve_status_by_event
{

    // 由事件解析 status（event → status 表——不讀現狀態、不落地）
    public function resolve_status_by_event($event)
    {
        return $this->event_to_status[$event] ?? "";
    }
}

<?php

namespace sc\app\hkai\plugins\sc_ai_chats\fsm;

// == sc_ai_chats fsm is_terminal_status：fsm 域查詢（trait——掛入 fsm_Class；檔＝動作） ==
// == 2026-09-27 自 cls/sc_ai_chats_fsm.php 分獨立檔（one file one function） ==

trait is_terminal_status
{

    // 此 status 是否終態（fsm_map status.terminal——唯一來源）
    public function is_terminal_status($status)
    {
        return in_array((string)$status, $this->terminal_status, true);
    }
}

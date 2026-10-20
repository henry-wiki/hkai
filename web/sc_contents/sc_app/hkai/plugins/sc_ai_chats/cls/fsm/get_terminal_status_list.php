<?php

namespace sc\app\hkai\plugins\sc_ai_chats\fsm;

// == sc_ai_chats fsm get_terminal_status_list：fsm 域查詢（trait——掛入 fsm_Class；檔＝動作） ==
// == 2026-09-29 新設（跨對話 SQL 要用終態清單——唔手抄詞彙，同源 fsm_map） ==

trait get_terminal_status_list
{

    // 終態清單（fsm_map status.terminal——唯一來源；供 SQL IN 參數用）
    public function get_terminal_status_list()
    {
        return $this->terminal_status;
    }

}

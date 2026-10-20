<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\view;

// == sc_ai_chats message view format_elapsed_sec：message 域動作（trait——掛入 view_Class；檔＝動作：秒→顯示時間） ==
// == 2026-10-07 新設：用時顯示單一轉換（段用時／總用時共用——m:ss） ==

trait format_elapsed_sec
{
    // 秒 → 顯示時間（m:ss——60 秒起跳分鐘；段用時計時器／總用時列共用同一格式）
    public function format_elapsed_sec($elapsed_sec)
    {
        return sprintf("%d:%02d", (int)floor($elapsed_sec / 60), (int)$elapsed_sec % 60);
    }
}

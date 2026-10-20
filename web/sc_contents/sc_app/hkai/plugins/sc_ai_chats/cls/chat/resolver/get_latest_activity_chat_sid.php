<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\resolver;

// == sc_ai_chats chat get_latest_activity_chat_sid：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/resolver.php 分獨立檔（one file one function） ==

trait get_latest_activity_chat_sid
{
    // 動作一：最近活動嘅對話 sid（形——未來：由 project_id 經 activity 表攞最近前往嘅對話；接入時 SQL 落 activity repo_select）
    // [ 備註 ] 未接入——現時一律 null（呼叫端 fallback 專案最新一個對話）
    public function get_latest_activity_chat_sid($project_id)
    {
        return null;
    }

}

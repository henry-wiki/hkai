<?php

namespace sc\app\hkai\plugins\sc_ai_chats\health;

// == sc_ai_chats health get_poll_report：health 域動作（trait——掛入 health_Class；檔＝動作） ==
// == 2026-09-28 health report（message_poll）——取 poll 域全部 jobs（經擁有者域對外方法） ==

trait get_poll_report
{
    // ==== report ====
    // 讀取全部對話嘅輪詢報告（poll 域對外取數——原樣輸出；請求資料＝health 原樣轉交——按需取用）
    public function get_poll_report($request_data)
    {
        return sc_ai_chats()->poll()->file()->get_all_poll_jobs();
    }
}

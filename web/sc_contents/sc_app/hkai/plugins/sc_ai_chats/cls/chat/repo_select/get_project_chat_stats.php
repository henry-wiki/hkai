<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_select;

// == sc_ai_chats chat get_project_chat_stats：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_chats/cls/chat/repo_select/ 分獨立檔（one file one function） ==

trait get_project_chat_stats
{

    // 專案統計（該專案對話列彙總——對話數＋等待列行數；供專案清單項目顯示）
    public function get_project_chat_stats($project_id)
    {
        $chat_rows  = sc_ai_chats()->chat()->get_chat_rows($project_id);
        $chat_count = count($chat_rows);

        $queue_count = 0;

        // 逐對話累加等待列行數（queue 檔——只讀檔）
        foreach ($chat_rows as $chat_row_item) {
            $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat((int)$chat_row_item["chat_id"]);
            $queue_items  = sc_ai_chats()->queue()->get_queue_items($project_slug, (string)$chat_row_item["chat_sid"]);
            $queue_count += count($queue_items);
        }

        return [
            "chat_count"  => $chat_count,
            "queue_count" => $queue_count,
        ];
    }

}

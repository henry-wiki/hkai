<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\repo_select;

// == sc_ai_chats chat get_project_chat_stats：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/repo_select.php 分獨立檔（one file one function） ==

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
            $queue_lines = sc_ai_chats()->queue()->get_queue_lines($chat_row_item["chat_id"]);
            $queue_count += count($queue_lines);
        }

        return [
            "chat_count"  => $chat_count,
            "queue_count" => $queue_count,
        ];
    }

}

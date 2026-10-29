<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue delete_queue_chat：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：清成個對話等待列（所有 pending 項目＋對話目錄） ==
// == 上游：handle_queue_clear／handle_chat_delete／delete_message；下游：get_queue_chat_dir ==

trait delete_queue_chat
{
    // 刪對話排隊目錄（全部項目）
    public function delete_queue_chat($project_slug, $chat_sid)
    {
        $queue_chat_dir = sc_ai_chats()->queue()->get_queue_chat_dir($project_slug, $chat_sid);

        // 判定：對話未有排隊目錄 → 唔動作
        if (!is_dir($queue_chat_dir)) {
            return;
        }

        $dir_names = @scandir($queue_chat_dir);

        // 判定：目錄讀唔到 → 唔動作
        if ($dir_names === false) {
            return;
        }

        // 逐個項目刪
        foreach ($dir_names as $dir_name_item) {
            // 判定：.／.. → 跳過
            if (in_array($dir_name_item, [".", ".."])) {
                continue;
            }

            $this->_delete_queue_item_dir($queue_chat_dir . "/" . $dir_name_item);
        }

        @rmdir($queue_chat_dir);
    }
}

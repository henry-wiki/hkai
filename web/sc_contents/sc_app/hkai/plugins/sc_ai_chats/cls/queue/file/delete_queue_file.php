<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue delete_queue_file：queue 域動作（trait——掛入 queue_Class；檔＝動作：刪成個排隊檔） ==
// == 2026-09-27 自 prompt/queue.php 分獨立域（檔＝動作） ==

trait delete_queue_file
{

    //---------------------------------------------------------
    // 1. 刪成個排隊檔
    //---------------------------------------------------------
    public function delete_queue_file($chat_id)
    {
        $queue_file = sc_ai_chats()->queue()->get_queue_file_path($chat_id);
        
        // 判定：檔案唔存在 → 唔動作
        if (!is_file($queue_file)) {
            return;
        }
        
        unlink($queue_file);
    }
}

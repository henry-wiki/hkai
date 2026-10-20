<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue append_queue_line：queue 域動作（trait——掛入 queue_Class；檔＝動作：排隊檔尾加一行（uid＋設定＋內容）） ==
// == 2026-09-27 自 prompt/queue.php 分獨立域（檔＝動作） ==

trait append_queue_line
{

    //---------------------------------------------------------
    // 1. 排隊檔尾加一行（uid＋設定＋內容）
    // 2. 回傳 uid
    //---------------------------------------------------------
    public function append_queue_line($chat_id, $prompt_message)
    {
        // 排隊期身份（shift 成功一刻先變真 message_id；隨機 hex——穩定、唔可預測）
        $queue_uid = "q_" . bin2hex(random_bytes(8));
        
        $messages_dir = sc_ai_chats()->storage()->get_messages_dir();
        
        // messages 目錄（唔存在＝建立）
        sc_mkdir($messages_dir);
        
        $queue_file = sc_ai_chats()->queue()->get_queue_file_path($chat_id);
        
        $prompt_message["queue_uid"] = $queue_uid;
        
        sc_append_jsonl($queue_file, $prompt_message);
        
        return $queue_uid;
    }
}

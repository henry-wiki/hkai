<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue get_queue_lines：queue 域動作（trait——掛入 queue_Class；檔＝動作：讀排隊檔（每行拆 uid／內容／設定）） ==
// == 2026-09-27 自 prompt/queue.php 分獨立域（檔＝動作） ==

trait get_queue_lines
{

    //---------------------------------------------------------
    // 1. 讀排隊檔
    // 2. 每行拆成 uid／內容／設定
    //---------------------------------------------------------
    public function get_queue_lines($chat_id)
    {
        $queue_file = sc_ai_chats()->queue()->get_queue_file_path($chat_id);
        
        // 判定：檔案唔存在 → 空清單
        if (!is_file($queue_file)) {
            return [];
        }
        
        $file_content = @file_get_contents($queue_file);
        // 判定：讀唔到 → 空清單
        if ($file_content === false) {
            return [];
        }
        
        $queue_lines = [];
        $file_lines = explode("\n", $file_content);
        
        // 逐行砌項目（append 順序＝送達順序——唔需排序）
        foreach ($file_lines as $line_text_item) {
            $line_text_item = trim($line_text_item);
            
            // 判定：空行 → 略過
            if ($line_text_item === "") {
                continue;
            }
            
            $line_data = json_decode($line_text_item, true);
            // 判定：解析唔到 → 略過
            if (!is_array($line_data)) {
                continue;
            }
            
            $line_conf_data = isset($line_data["conf_data"]) ? $line_data["conf_data"] : [];
            
            $queue_uid = isset($line_data["queue_uid"]) ? (string)$line_data["queue_uid"] : "";
            
            // 判定：舊行（冇 queue_uid）→ 用舊 message_id 砌穩定身份；兩者皆缺 → 略過
            if ($queue_uid === "") {
                $legacy_message_id = isset($line_conf_data["message_id"]) ? (int)$line_conf_data["message_id"] : 0;
                // 判定：連舊 id 都冇 → 垃圾行
                if ($legacy_message_id < 1) {
                    continue;
                }
                $queue_uid = "msg_" . $legacy_message_id;
            }
            
            $user_prompt = isset($line_data["user_prompt"]) ? $line_data["user_prompt"] : "";
            
            $queue_line = [];
            $queue_line["queue_uid"] = $queue_uid;
            $queue_line["content"]   = $user_prompt;
            $queue_line["conf_data"] = $line_conf_data;
            
            $queue_lines[] = $queue_line;
        }
        
        return $queue_lines;
    }
}

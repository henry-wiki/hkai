<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue remove_queue_line：queue 域動作（trait——掛入 queue_Class；檔＝動作：剔走目標 uid 嗰行（含私有 filter_queue_lines）） ==
// == 2026-09-27 自 prompt/queue.php 分獨立域（檔＝動作） ==

trait remove_queue_line
{

    //---------------------------------------------------------
    // 1. 鎖住個檔
    // 2. 剔走目標 uid 嗰行
    // 3. 空咗就連檔刪
    //---------------------------------------------------------
    public function remove_queue_line($chat_id, $queue_uid)
    {
        $queue_file = sc_ai_chats()->queue()->get_queue_file_path($chat_id);
        
        // 判定：檔案唔存在 → 唔動作
        if (!is_file($queue_file)) {
            return;
        }
        
        $file_handle = fopen($queue_file, "c+");
        // 判定：開唔到 → 唔動作
        if ($file_handle === false) {
            return;
        }
        
        // 判定：攞唔到檔案鎖 → 唔動作（下一拍重試）
        if (!flock($file_handle, LOCK_EX)) {
            fclose($file_handle);
            
            return;
        }
        
        // 鎖內：讀全部 → 過濾目標行 → 寫返
        $file_content = stream_get_contents($file_handle);
        $kept_lines   = $this->filter_queue_lines($file_content, (string)$queue_uid);
        
        ftruncate($file_handle, 0);
        rewind($file_handle);
        // 判定：仲有保留行 → 寫返
        if (count($kept_lines) > 0) {
            fwrite($file_handle, implode("\n", $kept_lines) . "\n");
        }
        fflush($file_handle);
        flock($file_handle, LOCK_UN);
        fclose($file_handle);

        // 判定：全清（冇保留行）→ 連檔移除（等待列冇行＝唔應該有檔；Windows 需先 fclose 再 unlink）
        if (count($kept_lines) < 1) {
            unlink($queue_file);
        }
    }
    //---------------------------------------------------------
    // 1. 剔走目標 uid 嗰行
    // 2. 回傳保留嘅行
    //---------------------------------------------------------
    private function filter_queue_lines($file_content, $queue_uid)
    {
        $kept_lines = [];
        $file_lines = explode("\n", $file_content);
        
        // 逐行過濾
        foreach ($file_lines as $line_text_item) {
            $line_text_item = trim($line_text_item);
            
            // 判定：空行 → 略過
            if ($line_text_item === "") {
                continue;
            }
            
            $line_data      = json_decode($line_text_item, true);
            $line_conf_data = (is_array($line_data) && isset($line_data["conf_data"])) ? $line_data["conf_data"] : [];
            
            $line_queue_uid = (is_array($line_data) && isset($line_data["queue_uid"])) ? (string)$line_data["queue_uid"] : "";
            
            // 判定：舊行（冇 queue_uid）→ 用舊 message_id 砌同款身份
            if ($line_queue_uid === "") {
                $legacy_message_id = isset($line_conf_data["message_id"]) ? (int)$line_conf_data["message_id"] : 0;
                $line_queue_uid    = $legacy_message_id > 0 ? "msg_" . $legacy_message_id : "";
            }
            
            // 判定：目標 uid → 唔保留
            if (
                $line_queue_uid !== ""
                && $line_queue_uid === $queue_uid
            ) {
                continue;
            }
            
            $kept_lines[] = $line_text_item;
        }
        
        return $kept_lines;
    }
}

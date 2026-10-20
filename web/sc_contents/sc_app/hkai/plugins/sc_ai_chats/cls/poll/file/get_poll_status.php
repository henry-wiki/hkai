<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_poll_status：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-09-27 自 poll/file.php 分獨立檔（one file one function） ==

trait get_poll_status
{

    // 讀取現時狀態（trim 全文 → 拆行 → 最後一行＝最新；檔唔存在＝空——從未派工／已歸檔，唔係一個狀態）
    public function get_poll_status($chat_id, $message_id)
    {
        $status_file = sc_ai_chats()->poll()->get_poll_file($chat_id, $message_id, "status");
        
        // 判定：檔唔存在 → 空
        if (!file_exists($status_file)) {
            return "";
        }
        
        $status_content = trim(file_get_contents($status_file));
        $status_lines   = preg_split('/\r\n|\r|\n/', $status_content);
        
        // 最後一行＝最新狀態（原樣回——狀態檔唯一寫入者係 Python FSM，唔做防禦檢查）
        return array_pop($status_lines);
    }

}

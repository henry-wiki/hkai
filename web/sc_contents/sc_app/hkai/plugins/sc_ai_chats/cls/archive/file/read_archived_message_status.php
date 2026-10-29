<?php

namespace sc\app\hkai\plugins\sc_ai_chats\archive\file;

// == sc_ai_chats archive read_archived_message_status：archive 域動作（trait——掛入 file_Class；檔＝動作） ==
// == 2026-10-27 自 message/archive 搬入 archive/file/（讀歸檔狀態） ==

trait read_archived_message_status
{
    //---------------------------------------------------------
    // 1. 讀 archive/ 嗰邊 status 嘅最後一行
    //---------------------------------------------------------
    public function read_archived_message_status($chat_id, $message_id)
    {
        // 歸檔目錄（archive/{project_slug}/{message_id}——歸檔側路徑單一來源）
        $archived_dir         = sc_ai_chats()->archive()->get_archived_poll_dir($chat_id, $message_id);
        $archived_status_file = $archived_dir . "/msg_status.txt";

        // 判定：無歸檔 status 檔 → ""
        if (!is_file($archived_status_file)) {
            return "";
        }

        $status_content = trim((string)@file_get_contents($archived_status_file));
        $status_lines   = preg_split('/\r\n|\r|\n/', $status_content);

        return (string)array_pop($status_lines);
    }

}

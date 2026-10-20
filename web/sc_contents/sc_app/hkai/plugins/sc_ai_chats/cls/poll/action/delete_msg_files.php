<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll delete_msg_files：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-09-27 自 poll/action.php 分獨立檔（one file one function） ==

trait delete_msg_files
{

    //---------------------------------------------------------
    // 1. 刪現行目錄 messages/polling/{project_slug}/{message_id}
    // 2. 刪歸檔目錄 archive/{project_slug}/{message_id}
    //---------------------------------------------------------
    public function delete_msg_files($chat_id, $message_id)
    {
        // 兩個 poll 目錄（現行 ＋ 歸檔——同名；位置一律由 poll 路徑層出）
        $poll_dir     = $this->get_poll_dir($chat_id, $message_id);
        $archived_dir = $this->get_archived_poll_dir($chat_id, $message_id);

        // 現行位置：所有進行中（未完成）嘅訊息目錄（messages/polling/{project_slug}/{message_id}）
        sc_ai_chats()->poll()->delete_poll_dir($poll_dir);

        // 歸檔位置：已完成（終態收尾後移入 archive）嘅記錄目錄（archive/{project_slug}/{message_id}）
        sc_ai_chats()->poll()->delete_poll_dir($archived_dir);
    }

}

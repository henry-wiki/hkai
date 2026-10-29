<?php

namespace sc\app\hkai\plugins\sc_ai_chats\archive\action;

// == sc_ai_chats archive delete_archive_files：archive 域動作（trait——掛入 archive_Class；檔＝動作） ==
// == 職責：刪歸檔訊息箱（archive/<project_slug>/<chat_sid>/<prompt_id>——整箱；路徑交 get_archived_dir_by_slug） ==

trait delete_archive_files
{
    // 刪歸檔訊息箱（目錄唔存在＝唔動作）
    public function delete_archive_files($project_slug, $message_id)
    {
        $archived_dir = sc_ai_chats()->archive()->get_archived_dir_by_slug($project_slug, $message_id);

        // 判定：目錄唔存在 → 唔動作
        if (!is_dir($archived_dir)) {
            return;
        }

        // 整箱刪（sc_rrmdir 遞迴——檔案＋子目錄）
        sc_rrmdir($archived_dir);
    }
}

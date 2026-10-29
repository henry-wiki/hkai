<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll delete_poll_files：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 職責：刪現行訊息箱（polling/<project_slug>/<chat_sid>/<prompt_id>——整箱；路徑交 get_poll_dir_by_slug） ==
// == 2026-10-27 由 delete_msg_files 改名（poll 只管自己嗰份；歸檔交 archive()->delete_archive_files） ==

trait delete_poll_files
{
    // 刪現行訊息箱（目錄唔存在＝唔動作）
    public function delete_poll_files($project_slug, $message_id)
    {
        $polling_dir = sc_ai_chats()->poll()->get_poll_dir_by_slug($project_slug, $message_id);

        // 判定：目錄唔存在 → 唔動作
        if (!is_dir($polling_dir)) {
            return;
        }

        // 整箱刪（sc_rrmdir 遞迴——檔案＋子目錄）
        sc_rrmdir($polling_dir);
    }

}

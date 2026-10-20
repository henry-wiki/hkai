<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll sweep_old_archived_messages：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-10-08 新設（health 動作掛點——清 old archive：已歸檔訊息目錄逾 1 日 → 整個刪） ==

trait sweep_old_archived_messages
{
    //---------------------------------------------------------
    // 1. 掃 archive 兩層（archive/{project_slug}/{message_id}/——GLOB_ONLYDIR）
    // 2. 目錄 mtime 逾 1 日（86400s）→ 交 delete_poll_dir 整個刪（逐檔刪＋刪空目錄）
    //---------------------------------------------------------
    public function sweep_old_archived_messages()
    {
        $daemon_config = hkai__core()->get_daemon_config();
        $archive_dir   = $daemon_config["daemon_archive_dir"];

        // 判定：archive 根唔存在 → 唔動作
        if (!is_dir($archive_dir)) {
            return;
        }

        $message_dirs = glob($archive_dir . "/*/*", GLOB_ONLYDIR);

        // 判定：glob 失敗 → 唔動作
        if ($message_dirs === false) {
            return;
        }

        $now = time();

        // 逐個訊息目錄檢查（mtime 逾 1 日 → 整個刪）
        foreach ($message_dirs as $message_dir_item) {
            $message_dir_mtime = @filemtime($message_dir_item);

            // 判定：mtime 讀唔到 → 跳過
            if ($message_dir_mtime === false) {
                continue;
            }

            // 判定：目錄年齡超過 1 日 → 整個刪（同 delete_msg_files 同一把刀）
            if (($now - $message_dir_mtime) > 86400) {
                $this->delete_poll_dir($message_dir_item);
            }
        }
    }

}

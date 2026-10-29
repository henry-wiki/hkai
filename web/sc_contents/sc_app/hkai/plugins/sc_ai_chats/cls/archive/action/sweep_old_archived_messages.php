<?php

namespace sc\app\hkai\plugins\sc_ai_chats\archive\action;

// == sc_ai_chats archive sweep_old_archived_messages：archive 域動作（trait——掛入 archive_Class；檔＝動作） ==
// == 2026-10-08 新設（health 動作掛點——清 old archive：已歸檔訊息目錄逾 1 日 → 整個刪；2026-10-27 自 poll 搬入） ==

trait sweep_old_archived_messages
{
    //---------------------------------------------------------
    // 1. 掃 archive 兩層（archive/{project_slug}/{message_id}/——GLOB_ONLYDIR）
    // 2. 目錄 mtime 逾 1 日（86400s）→ 交 sc_rrmdir 整個刪（遞迴）
    //---------------------------------------------------------
    public function sweep_old_archived_messages()
    {
        $archive_dir = sc_get_config("hkai.messages_archive_dir");

        // 判定：archive 根唔存在 → 唔動作
        if (!is_dir($archive_dir)) {
            return;
        }

        $message_dirs = [];

        // 掃兩層（舊：archive/<slug>/<message_id>）＋三層（新：archive/<slug>/<chat_sid>/<prompt_id>）
        $child_dirs = glob($archive_dir . "/*/*", GLOB_ONLYDIR);

        // 判定：有子目錄 → 分辨新舊
        if ($child_dirs !== false) {
            // 逐個子目錄：數字＝舊制；32 hex＝新制 chat_sid 層
            foreach ($child_dirs as $child_dir_item) {
                $child_name = basename($child_dir_item);

                // 判定：舊制訊息目錄 → 直接收
                if (ctype_digit($child_name)) {
                    $message_dirs[] = $child_dir_item;
                    continue;
                }

                // 判定：新制 chat_sid 層 → 收其下 prompt 目錄
                if (preg_match("/^[0-9a-f]{32}$/i", $child_name) === 1) {
                    $prompt_dirs = glob($child_dir_item . "/*", GLOB_ONLYDIR);

                    // 判定：有 prompt 目錄 → 合併
                    if ($prompt_dirs !== false) {
                        $message_dirs = array_merge($message_dirs, $prompt_dirs);
                    }
                }
            }
        }

        // 判定：glob 失敗 → 唔動作
        if ($message_dirs === false) {
            return;
        }

        $now = time();

        // 逐個訊息目錄檢查（mtime 逾 1 年 → 整個刪；結構未定稿前唔刪）
        foreach ($message_dirs as $message_dir_item) {
            $message_dir_mtime = @filemtime($message_dir_item);

            // 判定：mtime 讀唔到 → 跳過
            if ($message_dir_mtime === false) {
                continue;
            }

            // 判定：目錄年齡超過 1 年 → 整個刪（sc_rrmdir 遞迴）
            if (($now - $message_dir_mtime) > 31536000) {
                sc_rrmdir($message_dir_item);
            }
        }
    }

}

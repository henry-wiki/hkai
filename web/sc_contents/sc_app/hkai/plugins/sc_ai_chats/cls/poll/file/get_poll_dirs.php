<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_poll_dirs：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-09-27 自 web/sc_contents/sc_app/hkai/plugins/sc_ai_chats/cls/poll/file/_.bridge.cls.php 分獨立檔（one file one function） ==

trait get_poll_dirs
{
    // 訊息目錄清單（新三層 {slug}/{chat_sid}/{prompt_id} ＋ 舊兩層 {slug}/{message_id} 一律收）
    public function get_poll_dirs($chat_id = null)
    {
        $messages_dir = sc_ai_chats()->storage()->get_messages_dir();

        // 判定：指定對話 → 只掃該專案目錄
        if ($chat_id !== null) {
            $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);
            $project_dirs = [$messages_dir . "/" . $project_slug];
        } else {
            $project_dirs = glob($messages_dir . "/*", GLOB_ONLYDIR);
        }

        // 判定：專案清單掃唔到 → 空
        if ($project_dirs === false) {
            return [];
        }

        $message_dirs = [];

        // 逐個專案：分舊／新兩制收目錄
        foreach ($project_dirs as $project_dir_item) {
            $child_dirs = glob($project_dir_item . "/*", GLOB_ONLYDIR);

            // 判定：專案內冇子目錄 → 跳過
            if ($child_dirs === false) {
                continue;
            }

            // 逐個子目錄：數字＝舊制訊息目錄；32 hex＝新制 chat_sid 層
            foreach ($child_dirs as $child_dir_item) {
                $child_name = basename($child_dir_item);

                // 判定：舊制（message_id 命名）→ 直接收
                if (ctype_digit($child_name)) {
                    $message_dirs[] = $child_dir_item;
                    continue;
                }

                // 判定：新制（32 hex chat_sid）→ 再落一層收 prompt_id 目錄
                if (preg_match("/^[0-9a-f]{32}$/i", $child_name) === 1) {
                    $prompt_dirs = glob($child_dir_item . "/*", GLOB_ONLYDIR);

                    // 判定：有 prompt 目錄 → 合併
                    if ($prompt_dirs !== false) {
                        $message_dirs = array_merge($message_dirs, $prompt_dirs);
                    }
                }
            }
        }

        return $message_dirs;
    }

}

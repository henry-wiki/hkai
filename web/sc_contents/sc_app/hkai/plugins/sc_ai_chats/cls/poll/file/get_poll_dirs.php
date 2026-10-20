<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll file get_poll_dirs：file 子域動作（trait——合成入 file_Class；檔＝動作） ==
// == 2026-09-27 自 poll/file.php 分獨立檔（one file one function） ==

trait get_poll_dirs
{
    // 訊息目錄路徑清單（messages/polling/{project_slug}/{message_id} 目錄；指定 chat_id → 該專案；null → 全部專案）
    public function get_poll_dirs($chat_id = null)
    {
        $messages_dir = sc_ai_chats()->storage()->get_messages_dir();

        // 判定：指定對話 → 該對話專案目錄下嘅訊息目錄（單一專案掃描）
        if ($chat_id !== null) {
            $project_slug = sc_ai_chats()->poll()->get_project_slug_by_chat($chat_id);
            $message_dirs = glob($messages_dir . "/" . $project_slug . "/[0-9]*", GLOB_ONLYDIR);

            // 判定：glob 失敗（極少）→ 回空陣列（維持「回傳必為陣列」契約）
            if ($message_dirs === false) {
                $message_dirs = [];
            }

            return $message_dirs;
        }

        // null → 全部專案目錄（get_all_poll_jobs 全掃用）
        $message_dirs = [];
        $project_dirs = glob($messages_dir . "/*", GLOB_ONLYDIR);

        // 判定：專案目錄掃描失敗 → 回空陣列
        if ($project_dirs === false) {
            return $message_dirs;
        }

        foreach ($project_dirs as $project_dir_item) {
            $project_message_dirs = glob($project_dir_item . "/[0-9]*", GLOB_ONLYDIR);

            // 判定：該專案無訊息目錄 → 跳過
            if ($project_message_dirs === false) {
                continue;
            }

            $message_dirs = array_merge($message_dirs, $project_message_dirs);
        }

        return $message_dirs;
    }

}

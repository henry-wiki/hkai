<?php

namespace sc\app\hkai\plugins\sc_ai_chats\attachment\action;

// == sc_ai_chats attachment get_uploads_dir：attachment 域動作（trait——掛入 attachment_Class；檔＝動作） ==
// == 職責：上傳目錄（storage/folders.uploads/{project_slug}——單一組裝來源） ==

trait get_uploads_dir
{
    // 上傳目錄（每個專案一個——絕對路徑）
    public function get_uploads_dir($project_slug)
    {
        $folders = hkai__core()->get_hkai_configs("folders");
        $uploads_folder = $folders["uploads"];

        $storage_dir = sc_ai_chats()->storage()->get_storage_dir();

        return $storage_dir . "/" . $uploads_folder . "/" . $project_slug;
    }
}

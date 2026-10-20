<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\action;

// == sc_ai_chats poll delete_poll_dir：poll 域動作（trait——掛入 poll_Class；檔＝動作） ==
// == 2026-09-27 自 poll/action.php 分獨立檔（one file one function） ==

trait delete_poll_dir
{
    //---------------------------------------------------------
    // 1. 逐個檔刪
    // 2. 再刪空目錄
    //---------------------------------------------------------
    private function delete_poll_dir($poll_dir)
    {
        // 判定：目錄唔存在 → 唔動作
        if (!is_dir($poll_dir)) {
            return;
        }

        // 逐檔刪除（目錄制只有檔案，冇子目錄）
        $file_paths = glob($poll_dir . "/*");
        if ($file_paths === false) {
            $file_paths = [];
        }
        foreach ($file_paths as $file_path_item) {
            // 判定：係檔案 → 刪
            if (is_file($file_path_item)) {
                unlink($file_path_item);
            }
        }

        // 刪空目錄
        rmdir($poll_dir);
    }

}

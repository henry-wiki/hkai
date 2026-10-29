<?php

namespace sc\app\hkai\plugins\sc_ai_chats\queue\file;

// == sc_ai_chats queue remove_queue_item：queue 域動作（trait——掛入 queue_Class；檔＝動作） ==
// == 職責：剔走一條排隊項目（整個目錄——meta.json＋attachments）＋空對話目錄收走 ==
// == 上游：handle_queue_cancel／shift（進場後清走項目）；下游：get_queue_chat_dir ==

trait remove_queue_item
{
    // 按 queue_item_id 刪排隊項目（目錄名尾段＝-<queue_item_id>；搵唔到＝唔動作）
    public function remove_queue_item($project_slug, $chat_sid, $queue_item_id)
    {
        $queue_chat_dir = sc_ai_chats()->queue()->get_queue_chat_dir($project_slug, $chat_sid);

        // 判定：對話未有排隊目錄 → 唔動作
        if (!is_dir($queue_chat_dir)) {
            return;
        }

        $dir_names = @scandir($queue_chat_dir);

        // 判定：目錄讀唔到 → 唔動作
        if ($dir_names === false) {
            return;
        }

        // 逐個目錄搵目標（目錄名＝prompt_id——同 queue_item_id 直接比對）
        foreach ($dir_names as $dir_name_item) {
            // 判定：.／.. → 跳過
            if (in_array($dir_name_item, [".", ".."])) {
                continue;
            }

            // 判定：目錄名唔係目標 → 跳過
            if ($dir_name_item !== (string)$queue_item_id) {
                continue;
            }

            $this->_delete_queue_item_dir($queue_chat_dir . "/" . $dir_name_item);
        }

        // 清完冇項目 → 收走對話目錄（唔留空殼）
        $this->_remove_empty_queue_chat_dir($queue_chat_dir);
    }

    // 刪單一項目目錄（含包內 attachments）
    private function _delete_queue_item_dir($queue_item_dir)
    {
        // 判定：目錄唔存在 → 唔動作
        if (!is_dir($queue_item_dir)) {
            return;
        }

        $this->_delete_dir_tree($queue_item_dir);
    }

    // 遞迴刪目錄樹（先清檔案，再收目錄）
    private function _delete_dir_tree($dir_path)
    {
        $file_paths = glob($dir_path . "/*");

        // 判定：讀唔到 → 用空清單
        if ($file_paths === false) {
            $file_paths = [];
        }

        foreach ($file_paths as $file_path_item) {
            // 判定：子目錄 → 遞迴
            if (is_dir($file_path_item)) {
                $this->_delete_dir_tree($file_path_item);
            } else {
                @unlink($file_path_item);
            }
        }

        @rmdir($dir_path);
    }

    // 空對話目錄 → 收走
    private function _remove_empty_queue_chat_dir($queue_chat_dir)
    {
        $left_dirs = @scandir($queue_chat_dir);

        // 判定：讀唔到 → 唔動作
        if ($left_dirs === false) {
            return;
        }

        // 只剩 .／.. ＝ 空
        if (count($left_dirs) > 2) {
            return;
        }

        @rmdir($queue_chat_dir);
    }
}

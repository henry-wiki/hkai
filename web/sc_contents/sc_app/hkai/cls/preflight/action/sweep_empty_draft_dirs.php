<?php

namespace sc\app\hkai\cls\preflight\action;

// == sweep_empty_draft_dirs_Class：清道夫（空草稿目錄） ==
// == 職責：掃 data/messages/draft，由內向外刪走「完全冇內容」嘅目錄（只 rmdir，唔刪檔） ==
// == 上游：preflight_Class::run（每次頁面初始化一次） ==
// == 下游：hkai.messages_draft_dir（路徑單一來源） ==

class sweep_empty_draft_dirs_Class
{
    // 主動作（回刪走嘅目錄數；任何錯誤唔拋出——清道夫唔應該影響頁面）
    public function run()
    {
        $draft_root = sc_get_config("hkai.messages_draft_dir");

        // 判定：草稿根目錄唔存在 → 冇嘢可清
        if (is_dir($draft_root) === false) {
            return 0;
        }

        $removed_count = 0;

        // 逐專案目錄：由內向外清（專案層自己空都會被刪）
        foreach (glob($draft_root . "/*", GLOB_ONLYDIR) as $project_dir_item) {
            $removed_count = $removed_count + $this->remove_empty_tree($project_dir_item);
        }

        return $removed_count;
    }

    // 遞迴清空目錄樹（後序：先清細路，最後自己）
    private function remove_empty_tree($dir_path)
    {
        $entry_list = @scandir($dir_path);

        // 判定：讀唔到目錄 → 唔動（交下輪）
        if ($entry_list === false) {
            return 0;
        }

        $removed_count = 0;

        foreach ($entry_list as $entry_item) {
            // 判定：. ／ .. → 跳過
            if ($entry_item === "." || $entry_item === "..") {
                continue;
            }

            $child_path = $dir_path . "/" . $entry_item;

            // 判定：子項係目錄 → 先清佢
            if (is_dir($child_path) === true) {
                $removed_count = $removed_count + $this->remove_empty_tree($child_path);
            }
        }

        // 判定：自己已空 → 刪（只 rmdir，絕對唔會刪走檔案）
        if ($this->is_dir_empty($dir_path) === true) {
            @rmdir($dir_path);

            $removed_count = $removed_count + 1;
        }

        return $removed_count;
    }

    // 目錄係唔係完全空（只有 . 同 ..）
    private function is_dir_empty($dir_path)
    {
        $entry_list = @scandir($dir_path);

        // 判定：讀唔到 → 當「唔空」（唔好亂刪）
        if ($entry_list === false) {
            return false;
        }

        return count($entry_list) <= 2;
    }
}

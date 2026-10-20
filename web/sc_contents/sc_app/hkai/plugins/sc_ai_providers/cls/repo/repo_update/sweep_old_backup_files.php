<?php

namespace sc\app\hkai\plugins\sc_ai_providers\repo\repo_update;

// == sc_ai_providers repo sweep_old_backup_files：域動作（trait——掛入 repo_Class；檔＝動作） ==
// == 2026-10-08 新設（health 動作掛點——清舊備份：model_store 內逾 1 日 *.bak → 刪；清除統一歸清掃程序） ==

trait sweep_old_backup_files
{
    //---------------------------------------------------------
    // 1. 逐目錄（$dir_list——呼叫端比範圍）交單目錄清掃
    //---------------------------------------------------------
    public function sweep_old_backup_files($dir_list)
    {
        // 逐個目錄清掃（目錄唔存在＝helper 自守）
        foreach ($dir_list as $dir_item) {
            $this->sweep_backup_files_in_dir($dir_item);
        }
    }

    //---------------------------------------------------------
    // 1. glob 該目錄 *.bak
    // 2. filectime 逾 1 日（86400s）→ unlink
    //---------------------------------------------------------
    private function sweep_backup_files_in_dir($dir_item)
    {
        // 判定：目錄唔存在 → 唔動作
        if (!is_dir($dir_item)) {
            return;
        }

        $bak_paths = glob($dir_item . "/*.bak");

        // 判定：glob 失敗 → 唔動作
        if ($bak_paths === false) {
            return;
        }

        $now = time();

        // 逐個備份檔判定（ctime 逾 1 日 → 刪）
        foreach ($bak_paths as $bak_path_item) {
            $bak_ctime = @filectime($bak_path_item);

            // 判定：ctime 讀唔到 → 跳過
            if ($bak_ctime === false) {
                continue;
            }

            // 判定：備份年齡超過 1 日 → 刪
            if (($now - $bak_ctime) > 86400) {
                @unlink($bak_path_item);
            }
        }
    }

}

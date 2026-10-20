<?php

namespace sc\app\hkai\plugins\sc_ai_chats\chat\history;

// == sc_ai_chats chat cleanup_old_logs：chat 域動作（trait——掛入 chat_Class；檔＝動作） ==
// == 2026-09-27 自 chat/history.php 分獨立檔（one file one function） ==

trait cleanup_old_logs
{
    // 清理過期的 msg_* 記錄檔（歸檔時順手——由 archive_msg_files 尾叫；原屬 sc_ai_log plugin）
    // cooldown：marker {archive_dir}/.last_cleanup——mtime < 3600s → 跳過（唔會每次載入都掃）
    // 對象：archive 頂層、檔名 msg_ 開頭、mtime 超 86400s（1 日）嘅檔案（歸檔目錄唔掂）
    // 回傳：有刪 = ["last_run" => …, "deleted_count" => N]；無刪／跳過 = null
    public function cleanup_old_logs()
    {
        $daemon_config = hkai__core()->get_daemon_config();
        $archive_dir   = $daemon_config["daemon_archive_dir"];

        // archive 目錄不存在 → 直接返回
        if (!is_dir($archive_dir)) {
            return null;
        }

        $marker_file = $archive_dir . "/.last_cleanup";

        // cooldown 檢查：marker 存在且 mtime 在 3600s 內 → 跳過
        if (file_exists($marker_file)) {
            $marker_age = time() - @filemtime($marker_file);
            if ($marker_age < 3600) {
                return null;
            }
        }

        $files = @scandir($archive_dir);

        // 判定：目錄讀唔到 → 唔動作
        if ($files === false) {
            return null;
        }

        $deleted_count = 0;
        $now           = time();
        $max_age       = 86400;

        // 逐項檢查（跳過目錄／非 msg_／讀唔到 mtime）
        foreach ($files as $filename_item) {
            // 判定：. ／ .. ／非 msg_ 開頭 → 跳過
            if (
                $filename_item === "."
                || $filename_item === ".."
            ) {
                continue;
            }
            if (strpos($filename_item, "msg_") !== 0) {
                continue;
            }

            $filepath   = $archive_dir . "/" . $filename_item;
            $file_mtime = @filemtime($filepath);

            // 判定：mtime 讀唔到 → 跳過
            if ($file_mtime === false) {
                continue;
            }

            // 判定：檔案年齡超過 1 日 → 刪除
            if (($now - $file_mtime) > $max_age) {
                @unlink($filepath);
                $deleted_count++;
            }
        }

        @file_put_contents($marker_file, date("Y-m-d H:i:s"), LOCK_EX);

        // 判定：冇刪除 → 唔回報
        if ($deleted_count < 1) {
            return null;
        }

        return [
            "last_run"      => date("Y-m-d H:i:s"),
            "deleted_count" => $deleted_count,
        ];
    }

}

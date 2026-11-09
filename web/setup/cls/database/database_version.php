<?php

namespace sc\setup\database;

// == setup database version file：DB.VERSION 版本記錄檔（單元——class；呼叫形 \sc\setup\setup()->database_version()）
// == 職責：讀目前版本（最後一行）、追加版本、檔案缺失判定
// == 格式：每行一個版本，例 dbv-1.0 / dbv-1.2；最後一行＝目前版本
// == 上游：database 格新建庫（db_ready）、舊庫匯入（importer）、搬遷掃描（migration_source）
// == 下游：<data>/localdb/DB.VERSION（storage.database_version_file）

class database_version_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口）
     *
     * @return \sc\setup\database\database_version_Class
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // 讀目前版本（最後一行；冇檔／空白＝空字串）
    public function read_current_version($version_file)
    {
        // 判定：檔案唔存在 → 空
        if (is_file($version_file) === false) {
            return "";
        }

        $version_lines = file($version_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // 判定：冇任何行 → 空
        if (is_array($version_lines) === false || count($version_lines) < 1) {
            return "";
        }

        $last_line = trim($version_lines[count($version_lines) - 1]);

        return $this->strip_prefix($last_line);
    }

    // 追加版本（每次 migration／新裝後寫一行）
    public function append_version($version_file, $database_version)
    {
        $version_line = "dbv-" . $database_version . PHP_EOL;

        // 判定：檔案已存在 → 追加（保留歷史）
        if (is_file($version_file) === true) {
            file_put_contents($version_file, $version_line, FILE_APPEND | LOCK_EX);

            return;
        }

        file_put_contents($version_file, $version_line, LOCK_EX);
    }

    // 建立版本檔（新裝：寫入歷史＋目前版本）
    public function write_version_history($version_file, $version_list)
    {
        $version_lines = [];

        foreach ($version_list as $database_version) {
            $version_lines[] = "dbv-" . $database_version;
        }

        file_put_contents($version_file, implode(PHP_EOL, $version_lines) . PHP_EOL, LOCK_EX);
    }

    // 去掉 dbv- 前綴（只留 1.2）
    private function strip_prefix($version_line)
    {
        // 判定：有 dbv- 前綴 → 去掉
        if (strpos($version_line, "dbv-") === 0) {
            return substr($version_line, 4);
        }

        return $version_line;
    }
}

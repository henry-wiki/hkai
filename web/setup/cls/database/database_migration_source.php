<?php

namespace sc\setup\database;

// == setup database migration source：舊安裝搬遷掃描（單元——class；呼叫形 \sc\setup\setup()->database_migration_source()）
// == 職責：掃描舊安裝目錄 → 讀 DB.VERSION 版本 → 回資料庫位置（唯讀；其他資料不搬）
// == 版本規則：讀舊安裝根目錄 DB.VERSION 最後一行；冇檔＝基準版本（storage.database_version_base）
// == 偵測表：assets/detections/{version}__detect.php；版本冇對應檔＝用最低版本（0001）
// == 上游：database 格檢查（check_migration_source）、setup_install（source_mode＝import）
// == 下游：database_migrations（讀版本）、detections 目錄

class database_migration_source_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口）
     *
     * @return \sc\setup\database\database_migration_source_Class
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

    // 掃描舊安裝（回 {ok, detail_key, version, database_file}）
    public function scan_source($source_dir)
    {
        $source_dir = rtrim(trim($source_dir), "/\\");

        // 判定：空目錄字串 → fail
        if ($source_dir === "") {
            return $this->build_fail_result("detect.detail.empty");
        }

        // 判定：目錄唔存在 → fail
        if (is_dir($source_dir) === false) {
            return $this->build_fail_result("detect.detail.dir_not_found");
        }

        // 資料庫位置由「最低版本偵測檔」定義（固定契約——唔在程式寫死路徑）
        $base_detection = $this->load_base_detection();

        $database_file = $source_dir . "/" . $base_detection["database_file"];

        // 判定：舊安裝內冇資料庫 → fail
        if (is_file($database_file) === false) {
            return $this->build_fail_result("detect.detail.database_not_found");
        }

        $source_version = $this->read_source_version($source_dir);

        // 判定：讀到版本 → 用佢；冇 DB.VERSION＝舊版基準
        if ($source_version === "") {
            $source_version = sc_get_config("storage.database_version_base");
        }

        return [
            "ok"            => true,
            "version"       => $source_version,
            "database_file" => $database_file,
        ];
    }

    // 讀來源版本（舊安裝根目錄 DB.VERSION 最後一行；冇檔＝空）
    private function read_source_version($source_dir)
    {
        $source_version_file = $source_dir . "/DB.VERSION";

        return \sc\setup\setup()->database_version()->read_current_version($source_version_file);
    }

    // 取最低版本偵測檔（定資料庫固定路徑）
    private function load_base_detection()
    {
        $detections_dir = sc_get_config("storage.detections_dir");

        $detection_paths = glob($detections_dir . "/*_detect.php");

        sort($detection_paths);

        $detection = require $detection_paths[0];

        return $detection;
    }

    // 砌失敗結果（detail_key 交呼叫端出 i18n）
    private function build_fail_result($detail_key)
    {
        return ["ok" => false, "detail_key" => $detail_key];
    }
}

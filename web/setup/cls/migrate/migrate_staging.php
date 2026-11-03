<?php

namespace sc\setup\migrate;

// == HKAI setup migrate staging：升級匯入暫存區（class——Upgrade Review「下一步」時複製來源）
// == 職責：由舊安裝複製 DB／.env／DB.VERSION 到 data/setup_import/<時間>/（來源只讀）
// == 上游：validate_section（welcome／upgrade_review 通過後）、database_importer（Install 用）
// == 下游：state 記錄 staging 路徑；完成後 cleanup()

class migrate_staging_Class
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    // 準備暫存（回 staging 根路徑；失敗回 ""）
    public function prepare($staging_key, $source_dir)
    {
        $source_dir = rtrim(trim($source_dir), "/\\");

        // 判定：來源空 → 唔做
        if ($source_dir === "") {
            return "";
        }

        $staging_root = $this->get_staging_root($staging_key);
        $database_dir = $staging_root . "/data/localdb";
        $database_file = $database_dir . "/" . basename(sc_get_config("storage.database_file"));

        // 清空整個匯入區（唔止本 key——防止任何殘留舊資料）
        $this->remove_dir($this->get_import_base_dir());

        // 判定：建目錄失敗 → 回空
        if (@mkdir($database_dir, 0777, true) === false) {
            return "";
        }

        $source_scan = \sc\setup\setup()->database_migration_source()->scan_source($source_dir);

        // 判定：來源冇 DB → 唔複製
        if ($source_scan["ok"] !== true) {
            return "";
        }

        // 判定：資料庫複製失敗 → 回空
        if (@copy($source_scan["database_file"], $database_file) === false) {
            return "";
        }

        // 判定：舊安裝有 .env → 複製
        if (is_file($source_dir . "/.env") === true) {
            @copy($source_dir . "/.env", $staging_root . "/.env");
        }

        // 判定：舊安裝有 DB.VERSION → 複製（版本判斷用）
        if (is_file($source_dir . "/DB.VERSION") === true) {
            @copy($source_dir . "/DB.VERSION", $staging_root . "/DB.VERSION");
        }

        return $staging_root;
    }

    // 匯入區根（data/setup_import——每次複製前整區清空）
    public function get_import_base_dir()
    {
        $localdb_dir = dirname(sc_get_config("storage.database_file"));
        $data_dir    = dirname($localdb_dir);

        return $data_dir . "/setup_import";
    }

    // 暫存根（data/setup_import/<staging_key>）
    public function get_staging_root($staging_key)
    {
        $localdb_dir = dirname(sc_get_config("storage.database_file"));
        $data_dir    = dirname($localdb_dir);

        return $data_dir . "/setup_import/" . $staging_key;
    }

    // 讀暫存 .env 單一 key（Settings 預填——交 get_migrate_env 讀指定檔）
    public function get_env_value($source_dir, $env_key)
    {
        require_once __DIR__ . "/_.func.php";

        $staging_env_file = $this->get_staging_root(md5($source_dir)) . "/.env";

        $migrate_env = get_migrate_env($staging_env_file);

        return (string)$migrate_env->get($env_key, "");
    }

    // 完成後清理（只刪本暫存目錄；非空即遞歸刪）
    public function cleanup($staging_key)
    {
        $this->remove_dir($this->get_staging_root($staging_key));
    }

    // 安裝完成清理：整個匯入區一次清空（唔靠 key 對得上——殘留一律唔可以留）
    public function cleanup_all()
    {
        $this->remove_dir($this->get_import_base_dir());
    }

    // 遞歸刪（只內部用；不跟 symlink）
    private function remove_dir($dir_path)
    {
        // 判定：唔存在 → 唔做
        if (is_dir($dir_path) !== true) {
            return;
        }

        $entry_names = scandir($dir_path);

        // 判定：有內容 → 逐個刪（跳過 . 與 ..）
        if ($entry_names !== false) {
            foreach ($entry_names as $entry_name_item) {
                // 判定：. / .. → 跳
                if ($entry_name_item === "." || $entry_name_item === "..") {
                    continue;
                }

                $entry_path_item = $dir_path . "/" . $entry_name_item;

                // 判定：子目錄 → 遞歸
                if (is_dir($entry_path_item) === true) {
                    $this->remove_dir($entry_path_item);
                } else {
                    @unlink($entry_path_item);
                }
            }
        }

        @rmdir($dir_path);
    }
}

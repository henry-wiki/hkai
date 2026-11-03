<?php

namespace sc\setup\database;

// == setup database importer：舊資料庫匯入（單元——class；呼叫形 \sc\setup\setup()->database_importer()->import_database()）
// == 職責：複製舊庫副本 → 偵測版本 → 跑缺的 migration → 驗證行數 → 成功才換成正式 DB（原檔只讀）
// == 上游：database 格 setup_install（source_mode＝import）
// == 下游：storage.migrations_dir 的 *.sql.php、storage.database_file

class database_importer_Class
{
    private static $instance = null;

    /**
     * 單例（系統唯一入口）
     *
     * @return \sc\setup\database\database_importer_Class
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

    // 匯入（回 {ok, result, detail}；$source_dir＝舊安裝根目錄；$target_database_file 空＝正式 DB）
    public function import_database($source_dir, $target_database_file = "")
    {
        $database_file = sc_get_config("storage.database_file");

        // 判定：呼叫端指定目標（測試用）→ 用指定目標
        if ($target_database_file !== "") {
            $database_file = $target_database_file;
        }

        $source_scan_result = \sc\setup\setup()->database_migration_source()->scan_source($source_dir);

        // 判定：掃描失敗（冇可搬的資料庫）→ 交 i18n 訊息回
        if ($source_scan_result["ok"] !== true) {
            return $this->build_fail_result($source_scan_result["detail_key"]);
        }

        $source_path = $source_scan_result["database_file"];

        $source_check_result = $this->check_source_file($source_path);

        // 判定：來源唔合格 → 原果回
        if ($source_check_result["ok"] !== true) {
            return $source_check_result;
        }

        $import_root = dirname($database_file) . "/import/" . date("Ymd_His");

        // 判定：匯入目錄建立失敗 → fail
        if (is_dir($import_root) === false && @mkdir($import_root, 0777, true) === false) {
            return $this->build_fail_result("import.detail.dir_create_failed");
        }

        $import_file = $import_root . "/source.sqlite";

        // 判定：複製失敗 → fail（原檔只讀——copy 不會改來源）
        if (@copy($source_path, $import_file) === false) {
            return $this->build_fail_result("import.detail.copy_failed");
        }

        $target_schema_version = sc_get_config("storage.database_schema_version");

        $pdo_connection = null;

        try {
            $pdo_connection = new \PDO("sqlite:" . $import_file);

            $pdo_connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo_connection->exec("PRAGMA busy_timeout = 5000");

            // 判定：來源庫冇 meta 表 → 唔似 HKAI 資料庫 → fail
            if (\sc\setup\setup()->database_meta()->has_meta_table($pdo_connection) === false) {
                $pdo_connection = null;

                return $this->build_fail_result("import.detail.not_hkai_database");
            }

            $source_schema_version = $source_scan_result["version"];

            // 判定：來源版本比程式新 → fail（唔可以倒退）
            if (version_compare($source_schema_version, $target_schema_version) > 0) {
                $pdo_connection = null;

                return $this->build_fail_result("import.detail.source_too_new");
            }

            $before_table_counts = $this->read_table_counts($pdo_connection);

            $pdo_connection->beginTransaction();

            // 套用缺的 migration（逐檔執行＋逐檔記錄；檔名＝版本識別）
            \sc\setup\setup()->database_migrations()->apply_pending_migrations($pdo_connection);

            // 專案路徑改寫（home 指向本機 playground——舊安裝路徑帶過來會失效）
            $this->rewrite_home_project_path($pdo_connection);

            // 管理資料（來源路徑／結構簽名／安裝時間）
            \sc\setup\setup()->database_meta()->write_install_meta($pdo_connection, "import", $source_path);

            $pdo_connection->commit();

            $after_table_counts = $this->read_table_counts($pdo_connection);

            // 判定：原有表行數有變 → fail（資料唔可以無故消失）
            if ($this->is_table_counts_changed($before_table_counts, $after_table_counts) === true) {
                $pdo_connection = null;

                return $this->build_fail_result("import.detail.count_mismatch");
            }

            $pdo_connection = null;
        } catch (\Throwable $exception) {
            // 判定：交易進行中 → 回滾（副本保持原狀）
            if ($pdo_connection instanceof \PDO && $pdo_connection->inTransaction()) {
                $pdo_connection->rollBack();
            }

            $pdo_connection = null;

            return $this->build_fail_result("import.detail.migration_failed");
        }

        $this->backup_existing_database($database_file);

        // 換檔（Windows 目標被鎖時 rename 會失敗——先清 WAL／SHM，再移除舊目標重試一次）
        $is_replaced = @rename($import_file, $database_file);

        // 判定：首次失敗 → 目標先移做備份（唔直接刪——失敗可還原）再試
        $target_backup_file = $database_file . ".pre_import";

        if ($is_replaced === false) {
            @unlink($database_file . "-wal");
            @unlink($database_file . "-shm");
            @rename($database_file, $target_backup_file);

            $is_replaced = @rename($import_file, $database_file);

            // 判定：仍失敗 → 還原舊目標
            if ($is_replaced === false) {
                @rename($target_backup_file, $database_file);
            }
        }

        // 判定：成功且有備份 → 移除備份
        if ($is_replaced === true && is_file($target_backup_file)) {
            @unlink($target_backup_file);
        }

        // 判定：仍然失敗 → fail（附原因／建議）
        if ($is_replaced === false) {
            $replace_error = error_get_last();
            $replace_reason = (string)($replace_error["message"] ?? "");

            $fail_result = $this->build_fail_result("import.detail.replace_failed");
            $fail_result["hint"] = \sc\setup\setup()->i18n()->get("import.hint.replace_failed");
            $fail_result["detail"] = $fail_result["detail"] . " (" . $replace_reason . ")";

            return $fail_result;
        }

        // 清理：副本已換入正式位置 → 移除空白 import 目錄（唔遞歸、唔刪非空）
        @rmdir($import_root);

        // 判定：正式安裝 → 建立／追加版本記錄
        if ($target_database_file === "") {
            $this->write_database_version($target_schema_version);
        }

        $detail = \sc\setup\setup()->i18n()->get("import.detail.success");

        return ["ok" => true, "result" => "imported", "detail" => $detail];
    }

    // 來源檢查（存在／檔案／可讀／SQLite 檔頭）
    private function check_source_file($source_path)
    {
        // 判定：空路徑／唔存在／唔係檔 → fail
        if ($source_path === "" || is_file($source_path) === false) {
            return $this->build_fail_result("import.detail.source_not_found");
        }

        // 判定：唔可讀 → fail
        if (is_readable($source_path) === false) {
            return $this->build_fail_result("import.detail.source_not_readable");
        }

        $file_handle = @fopen($source_path, "rb");

        // 判定：開唔到 → fail
        if ($file_handle === false) {
            return $this->build_fail_result("import.detail.source_not_readable");
        }

        $file_header = fread($file_handle, 16);

        fclose($file_handle);

        // 判定：唔係 SQLite → fail
        if ($file_header !== "SQLite format 3" . chr(0)) {
            return $this->build_fail_result("import.detail.source_not_sqlite");
        }

        return ["ok" => true];
    }

    // 讀各表行數（唔包 migrations／meta／sqlite_sequence——由匯入流程管理）
    private function read_table_counts($pdo_connection)
    {
        $table_names = $pdo_connection->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);

        $table_counts = [];

        foreach ($table_names as $table_name) {
            // 判定：系統表／管理表（migrations／meta 由匯入流程寫入）→ 跳
            if (in_array($table_name, ["migrations", "meta", "sqlite_sequence"], true) === true) {
                continue;
            }

            $table_counts[$table_name] = (int)$pdo_connection->query("SELECT COUNT(*) FROM \"" . $table_name . "\"")->fetchColumn();
        }

        return $table_counts;
    }

    // 比較行數（任何原有表行數改變＝true）
    private function is_table_counts_changed($before_table_counts, $after_table_counts)
    {
        foreach ($before_table_counts as $table_name => $before_count) {
            $after_count = $after_table_counts[$table_name] ?? null;

            // 判定：表消失或行數唔同 → 有變
            if ($after_count === null || $after_count !== $before_count) {
                return true;
            }
        }

        return false;
    }

    // 寫版本記錄（冇檔先寫基準行；再追加目前版本）
    private function write_database_version($target_schema_version)
    {
        $database_version = \sc\setup\setup()->database_version();
        $version_file     = sc_get_config("storage.database_version_file");
        $version_base     = sc_get_config("storage.database_version_base");

        // 判定：版本檔唔存在 → 先寫基準（舊版歷史）
        if (is_file($version_file) === false) {
            $database_version->write_version_history($version_file, [$version_base]);
        }

        $database_version->append_version($version_file, $target_schema_version);
    }

    // 改寫 home 專案路徑（新站 data/playground——舊安裝路徑唔可以照抄）
    private function rewrite_home_project_path($pdo_connection)
    {
        $site_root        = dirname(SC_WEB_ROOT);
        $playground_path  = str_replace("\\", "/", $site_root) . "/data/playground";

        $pdo_connection->prepare("UPDATE projects SET project_path = ? WHERE project_slug = 'home'")->execute([$playground_path]);
    }

    // 備份現有正式 DB（有才備；放 localdb/_backup/）
    private function backup_existing_database($database_file)
    {
        // 判定：冇現有 DB → 唔使備
        if (is_file($database_file) === false) {
            return;
        }

        $backup_dir = dirname($database_file) . "/_backup";

        // 判定：備份目錄唔存在 → 建立
        if (is_dir($backup_dir) === false) {
            @mkdir($backup_dir, 0777, true);
        }

        $backup_file = $backup_dir . "/hkai_chat_data-" . date("Ymd_His") . ".sqlite";

        @copy($database_file, $backup_file);
    }

    // 砌失敗結果（detail 由 i18n 出）
    private function build_fail_result($detail_key)
    {
        $detail = \sc\setup\setup()->i18n()->get($detail_key);

        return ["ok" => false, "result" => "failed", "detail" => $detail];
    }
}

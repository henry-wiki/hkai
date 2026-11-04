<?php

namespace sc\setup\sections\database;

// == HKAI setup 檢查單元：database.db_ready.php（資料庫格「準備資料庫」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕（存在＝警告〔字眼喺 input_msg〕；唔存在＝正常）；setup_test()＝唯一真實檢查動作（檔案存在——回原果；狀態記錄喺安裝段步驟②）；setup_install()＝建立／確保資料庫（建檔〔如缺〕＋表結構——單一實現）（單一實現——檔＋表結構）
// == 接入：database 格檢查表（closure）＋sections 域派發（get_section_unit_instance("database", "db_ready")）
// == 載入：顯式（無 autoloader）——database 格本體（database.php）頂載

class database_db_ready_Class
{
    private static $instance = null;

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

    // 測試（唯一真實檢查動作——資料庫檔存在？回原果；$input_value 唔用〔路徑固定——值只為介面一致〕）
    public function setup_test($input_value)
    {
        $database_file = sc_get_config("storage.database_file");

        // 判定：資料庫檔存在且已有表 → 原果 ok（非空＝已有資料）
        if (is_file($database_file) === true && $this->has_existing_tables($database_file) === true) {
            return ["ok" => true];
        }

        // 其餘（未建立）→ 原果唔 ok
        return ["ok" => false];
    }

    // 有冇既有表（空檔／冇表＝false；開唔到＝當有，寧擋唔覆蓋）
    private function has_existing_tables($database_file)
    {
        try {
            $pdo_connection = new \PDO("sqlite:" . $database_file);
            $table_count    = (int)$pdo_connection->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
            $pdo_connection = null;

            return $table_count > 0;
        } catch (\Throwable $exception) {
            return true;
        }
    }

    // 備份既有資料庫：先 checkpoint（WAL 落主檔）→ 再連 -wal／-shm 一齊改名
    // 上游：install() 全新安裝遇到既有非空庫
    // 下游：checkpoint_database()、rename()
    private function backup_database_files($database_file, $database_backup_file)
    {
        $this->checkpoint_database($database_file);

        $suffix_list = ["", "-wal", "-shm"];

        // 逐個相關檔改名（WAL／SHM 唔跟走＝新庫接手舊交易日誌）
        foreach ($suffix_list as $suffix) {
            $source_file = $database_file . $suffix;

            // 判定：呢個檔唔存在 → 跳過
            if (is_file($source_file) === false) {
                continue;
            }

            // 判定：改名失敗 → 短暫重試（其他程序讀取中嘅鎖通常一兩秒內放開）
            if ($this->rename_with_retry($source_file, $database_backup_file . $suffix) === false) {
                return false;
            }
        }

        return true;
    }

    // 改名（Windows 檔案鎖：其他程序讀取中會短暫鎖住——重試 5 次，每次 200ms）
    // 上游：backup_database_files()
    // 下游：rename()
    private function rename_with_retry($source_file, $target_file)
    {
        $attempt_index = 0;

        // 逐次嘗試（最多 5 次）
        while ($attempt_index < 5) {
            $rename_result = @rename($source_file, $target_file);

            // 判定：改名成功 → 完成
            if ($rename_result === true) {
                return true;
            }

            $attempt_index = $attempt_index + 1;

            usleep(200000);
        }

        return false;
    }

    // WAL checkpoint：把交易日誌併入主檔並截斷（rename 前必要——否則 -wal 仍持有內容）
    // 上游：backup_database_files()
    // 下游：PDO PRAGMA wal_checkpoint(TRUNCATE)
    private function checkpoint_database($database_file)
    {
        try {
            $pdo_connection = new \PDO("sqlite:" . $database_file);
            $pdo_connection->query("PRAGMA wal_checkpoint(TRUNCATE)");
            $pdo_connection = null;
        } catch (\Throwable $exception) {
            // checkpoint 失敗唔即時擋——下面 rename 會如實反映結果
        }
    }

    // 檢查（存在＝警告〔字眼喺 input_msg·is-warn〕；唔存在＝正常）
    // 安裝步驟資料（標題／行值——值由本單元自決：記錄值／未填＝留空字眼）
    public function get_install_step_data()
    {
        $database_file_recorded_value = setup()->state()->get_setting_setup_process("database", "db_ready");

        $install_step_value = $database_file_recorded_value;

        // 判定：未填（空）→ 留空字眼
        if ($install_step_value === "") {
            $install_step_value = setup()->i18n()->get("install.value_empty");
        }

        $install_step_label = setup()->i18n()->get("install.step.database_file");

        return ["label" => $install_step_label, "value" => $install_step_value];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "pass";
        $check_result["label"] = setup()->i18n()->get("check.database_file.label");

        $database_file = sc_get_config("storage.database_file");
        $web_root      = sc_get_config("web_root");

        // 顯示用：完整路徑（含檔名——由 /web 起）
        $database_display_path = str_replace($web_root, "/web", $database_file);

        // 記錄值優先（setup_process.json——用戶測試過嘅值；冇記錄＝空串）
        $database_recorded_value = setup()->state()->get_setting_setup_process("database", "db_ready");

        // 顯示值（記錄優先——記錄 > 路徑示位）
        $database_display_value = $database_display_path;

        // 判定：有記錄 → 記錄優先
        if ($database_recorded_value !== "") {
            $database_display_value = $database_recorded_value;
        }

        // 輸入組＝路徑示位（唯讀——更新掣照有）
        $database_input_placeholder = setup()->i18n()->get("check.database_file.placeholder");

        $check_result["input"] = [
            "name"        => "db_ready",
            "value"       => $database_display_value,
            "readonly"    => true,
            "placeholder" => $database_input_placeholder,
        ];

        // 核心存在檢查＝唯一機制（setup_test——原果）
        $test_result = $this->setup_test("");

        // 判定：資料庫檔存在且有表 → warn（提示會自動改名備份；用戶揀全新安裝＝可以繼續）
        if ($test_result["ok"] === true) {
            $check_result["status"] = "warn";
            $check_result["input"]["msg"]       = setup()->i18n()->get("check.database_file.exists");
            $check_result["input"]["msg_class"] = "is-warn";

            return $check_result;
        }

        // 其餘（未建立）→ pass（正常狀態——唔出任何字）
        return $check_result;
    }

    // 安裝（建立／確保資料庫——建檔〔如缺〕＋表結構（database_schema.php）；單一實現·冪等可重入）
    public function setup_install()
    {
        $source_mode = setup()->state()->get_setting_setup_process("welcome", "source_mode");
        $source_dir = setup()->state()->get_setting_setup_process("welcome", "source_dir");

        // 判定：用戶揀搬遷 → 先掃描
        if ($source_mode === "import") {
            $source_scan_result = setup()->database_migration_source()->scan_source($source_dir);

            // 判定：掃描失敗（目錄不存在／找不到資料庫）→ 安裝失敗（唔改用新庫）
            if ($source_scan_result["ok"] !== true) {
                $scan_detail = setup()->i18n()->get($source_scan_result["detail_key"]);

                return ["ok" => false, "result" => "failed", "detail" => $scan_detail];
            }

            // 安裝改讀暫存複本（Upgrade Review 時已複製；冇暫存＝直接用來源）
            $staging_root = setup()->staging()->get_staging_root(md5($source_dir));
            $import_source_dir = $source_dir;

            if (is_file($staging_root . "/data/localdb/" . basename(sc_get_config("storage.database_file"))) === true) {
                $import_source_dir = $staging_root;
            }

            $import_result = setup()->database_importer()->import_database($import_source_dir);

            // 判定：匯入成功 → 清暫存（複本已換入正式位置）
            if ($import_result["ok"] === true) {
                setup()->staging()->cleanup_all();
            }

            return $import_result;
        }

        $database_file = sc_get_config("storage.database_file");
        $database_dir  = dirname($database_file);

        // 判定：全新安裝但已有非空資料庫 → 改名備份（唔覆蓋、唔刪除）再建新庫
        if (is_file($database_file) === true && $this->has_existing_tables($database_file) === true) {
            $database_backup_file = $database_file . ".bak-" . date("Y-m-d_His");

            // 判定：改名失敗 → 安裝失敗（唔可以覆蓋原庫）
            if ($this->backup_database_files($database_file, $database_backup_file) === false) {
                $rename_fail_detail = setup()->i18n()->get("install.detail.database_backup_failed");

                return ["ok" => false, "result" => "failed", "detail" => $rename_fail_detail];
            }
        }

        // 判定：資料庫檔唔存在 → 先備好目錄＋空檔（單項自足——唔靠前一項）
        if (!is_file($database_file)) {
            // 判定：localdb 目錄唔存在 → 建立
            if (!is_dir($database_dir)) {
                $dir_created = @mkdir($database_dir, 0777, true);

                // 判定：目錄建立失敗 → fail
                if ($dir_created === false) {
                    $dir_create_fail_detail = setup()->i18n()->get("install.detail.database_dir_create_failed");

                    return ["ok" => false, "result" => "failed", "detail" => $dir_create_fail_detail];
                }
            }

            // 建立空 DB 檔（用 fopen 建立——唔用 touch 改時間戳）
            $file_handle = @fopen($database_file, "cb");

            $file_created = ($file_handle !== false);

            // 判定：建立成功 → 即刻關閉
            if ($file_created === true) {
                fclose($file_handle);
            }

            // 判定：空檔建立失敗 → fail
            if ($file_created === false) {
                $file_create_fail_detail = setup()->i18n()->get("install.detail.database_file_create_failed");

                return ["ok" => false, "result" => "failed", "detail" => $file_create_fail_detail];
            }
        }

        $pdo = null;

        try {
            $pdo = new \PDO("sqlite:" . $database_file);

            // 錯誤模式＝例外（PHP 7.3 預設靜默——SQL 失敗唔會 throw，交易／回滾形同虛設）
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // 表結構（單一來源＝setup/assets/database_schema.sql.php——回傳全份 SQL）
            $database_schema_sql = require dirname(__DIR__, 3) . "/assets/database_schema.sql.php";

            // 全份 schema SQL 包一個交易（一次過執行；失敗＝回滾——唔會留半建結構）
            $pdo->beginTransaction();
            $pdo->exec($database_schema_sql);

            // 記錄全部 migration 為已套用（schema 檔已建最新結構——只記錄、不執行）
            \sc\setup\setup()->database_migrations()->record_all_as_applied($pdo);

            // 管理資料（結構簽名／來源／安裝時間）
            \sc\setup\setup()->database_meta()->write_install_meta($pdo, "default", "");

            // 版本記錄（DB.VERSION：最後一行＝目前版本）
            $version_file   = sc_get_config("storage.database_version_file");
            $version_base   = sc_get_config("storage.database_version_base");
            $target_version = sc_get_config("storage.database_schema_version");

            // 判定：版本檔唔存在 → 先寫基準行
            if (is_file($version_file) === false) {
                \sc\setup\setup()->database_version()->write_version_history($version_file, [$version_base]);
            }

            \sc\setup\setup()->database_version()->append_version($version_file, $target_version);

            $pdo->commit();

        } catch (\Throwable $exception) {
            // 判定：連線已建立 → 交易未完成時回滾（保持原狀——唔留半建結構）
            if ($pdo instanceof \PDO) {
                // 判定：交易進行中 → 回滾
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }

            $schema_fail_detail = setup()->i18n()->get("install.detail.database_schema_failed");
            $schema_fail_detail = $schema_fail_detail . $exception->getMessage();

            return ["ok" => false, "result" => "failed", "detail" => $schema_fail_detail];
        }

        $schema_ok_detail = setup()->i18n()->get("install.detail.database_schema_ok");

        return ["ok" => true, "result" => "created", "detail" => $schema_ok_detail];
    }
}

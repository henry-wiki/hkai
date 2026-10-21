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

        // 判定：資料庫檔存在 → 原果 ok
        if (is_file($database_file)) {
            return ["ok" => true];
        }

        // 其餘（未建立）→ 原果唔 ok
        return ["ok" => false];
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

        // 判定：資料庫檔存在 → warn（警告字眼由 i18n 字典出——zh-HK／en-US 對照；顯示跟記錄語言）
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
        $database_file = sc_get_config("storage.database_file");
        $database_dir  = dirname($database_file);

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

            $file_created = @touch($database_file);

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

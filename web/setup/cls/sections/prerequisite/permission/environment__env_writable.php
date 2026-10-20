<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__env_writable.php（前置檢查「環境檔 .env（寫入權限）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：permission()->environment__env_writable()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__env_writable_Class
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

    // 測試（唯一真實檢查動作——.env 寫入權限判定；回原果）
    public function setup_test()
    {
        $env = sc_get_config("core.env");

        // 判定：root（.env 所在目錄）可寫 → 原果 pass（可建立／可改）
        $env_dir = dirname($env);

        if (is_writable($env_dir)) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => $env . setup()->i18n()->get("check.permission.detail_writable"), "reading" => ""];
        }

        // 判定：root 唔可寫但檔本身可寫 → 原果 pass（改現檔都夠）
        if (is_writable($env)) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => $env . setup()->i18n()->get("check.permission.detail_writable"), "reading" => ""];
        }

        // 其餘（都唔可寫）→ 原果 warn（非必須——唔阻運行）
        return ["ok" => false, "status" => "warn", "hint" => "", "detail" => "", "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    // 安裝步驟資料（標題／行值——框架步・檢查項：冇輸入值，行值留空）
    public function get_install_step_data()
    {
        return ["label" => setup()->i18n()->get("install.step.env_writable"), "value" => ""];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "warn";
        $check_result["label"] = setup()->i18n()->get("check.permission.env_writable.label");

        $test_result = $this->setup_test();
        $test_status = $test_result["status"];
        $test_hint   = $test_result["hint"];
        $test_detail = $test_result["detail"];

        $check_result["status"] = $test_status;

        // 判定：原果帶 hint → 入對應格（force layout 用）
        if ($test_hint !== "") {
            $check_result["result_content"][$test_status]["hint"] = $test_hint;
        }

        // 判定：原果帶 detail → 入對應格
        if ($test_detail !== "") {
            $check_result["result_content"][$test_status]["detail"] = $test_detail;
        }

        return $check_result;
    }

    // 安裝（.env 已存在＝先改名備份〔.env.bak.{YYYYMMDD_HHMMSS}〕再由範本產生全新 .env；未建立＝直接產生——回動作原果）
    public function setup_install()
    {
        $env         = sc_get_config("core.env");
        $env_example = sc_get_config("core.env_example");

        // 判定：範本唔存在 → fail（冇範本唔建立——唔好出半份）
        if (!is_file($env_example)) {
            return ["ok" => false, "result" => "failed", "detail" => setup()->i18n()->get("install.detail.env_writable_template_missing")];
        }

        $backup_file = "";
        $backup_note = "";

        // 判定：.env 已存在 → 先改名備份（同目錄＝原子改名；備份命名帶時間——唔會撞名）
        if (is_file($env)) {
            $backup_file = $this->make_env_backup_file($env);

            $renamed = @rename($env, $backup_file);

            // 判定：備份失敗 → fail（未動任何檔案——最安全）
            if ($renamed === false) {
                return ["ok" => false, "result" => "failed", "detail" => setup()->i18n()->get("install.detail.env_writable_backup_failed")];
            }

            $backup_note_prefix = setup()->i18n()->get("install.detail.env_writable_backup_note_prefix");
        $backup_note_suffix = setup()->i18n()->get("install.detail.env_writable_backup_note_suffix");
        $backup_note = $backup_note_prefix . basename($backup_file) . $backup_note_suffix;
        }

        $created = @copy($env_example, $env);

        // 判定：建立失敗 → fail
        if ($created === false) {
            return ["ok" => false, "result" => "failed", "detail" => $backup_note . setup()->i18n()->get("install.detail.env_writable_create_failed")];
        }

        // 判定：有備份 → 回帶備份資訊
        if ($backup_file !== "") {
            return ["ok" => true, "result" => "backed_up", "backup_file" => basename($backup_file), "detail" => $backup_note . setup()->i18n()->get("install.detail.env_writable_recreated")];
        }

        return ["ok" => true, "result" => "created", "detail" => setup()->i18n()->get("install.detail.env_writable_created")];
    }

    // 出備份檔名（.env.bak.{YYYYMMDD_HHMMSS}——同名已存在＝加序號，唔覆蓋任何舊備份）
    private function make_env_backup_file($env)
    {
        $backup_file = $env . ".bak." . date("Ymd_His");

        // 判定：同名備份已存在（同秒重裝）→ 加序號
        if (is_file($backup_file)) {
            $backup_sequence = 2;

            // 逐個序號試（唔覆蓋任何舊備份）
            while (is_file($backup_file . "_" . $backup_sequence)) {
                $backup_sequence = $backup_sequence + 1;
            }

            $backup_file = $backup_file . "_" . $backup_sequence;
        }

        return $backup_file;
    }
}

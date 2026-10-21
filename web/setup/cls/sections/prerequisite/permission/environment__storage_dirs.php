<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__storage_dirs.php（前置檢查「目錄 data（存在＋寫入權限）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：permission()->environment__storage_dirs()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__storage_dirs_Class
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

    // 測試（唯一真實檢查動作——data 目錄存在兼可寫？〔含真寫入探測〕回原果）
    public function setup_test()
    {
        $app_dir = sc_get_config("storage.app_dir");

        // 判定：目錄唔存在 → 原果 fail（唔會代建）
        if (!is_dir($app_dir)) {
            return ["ok" => false, "status" => "fail", "hint" => "", "detail" => $app_dir . setup()->i18n()->get("check.permission.storage_dirs.detail_not_exist"), "reading" => ""];
        }

        // 判定：目錄唔可寫 → 原果 fail
        if (!is_writable($app_dir)) {
            return ["ok" => false, "status" => "fail", "hint" => "", "detail" => $app_dir . setup()->i18n()->get("check.permission.storage_dirs.detail_not_writable"), "reading" => ""];
        }

        // 實際寫入測試（暫存檔——寫入即刪；驗證真正可寫——is_writable 唔可靠）
        $write_probe_file = $app_dir . "/.write_probe_" . uniqid();
        $write_probe_ok   = @file_put_contents($write_probe_file, "probe");

        // 判定：寫入成功 → 刪返（零殘留）
        if ($write_probe_ok !== false) {
            @unlink($write_probe_file);
        }

        // 判定：寫入失敗 → 原果 fail（權限異常）
        if ($write_probe_ok === false) {
            return ["ok" => false, "status" => "fail", "hint" => "", "detail" => $app_dir . setup()->i18n()->get("check.permission.storage_dirs.detail_write_test_failed"), "reading" => ""];
        }

        // 其餘 → 原果 pass（全路徑示位）
        return ["ok" => true, "status" => "pass", "hint" => "", "detail" => $app_dir . setup()->i18n()->get("check.permission.detail_writable"), "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    // 安裝步驟資料（標題／行值——框架步・檢查項：冇輸入值，行值留空）
    public function get_install_step_data()
    {
        return ["label" => setup()->i18n()->get("install.step.storage_dirs"), "value" => ""];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "warn";
        $check_result["label"] = setup()->i18n()->get("check.permission.storage_dirs.label");

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
}

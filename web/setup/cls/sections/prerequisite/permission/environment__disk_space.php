<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__disk_space.php（前置檢查「磁碟空間（建議 1GB 或以上）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：permission()->environment__disk_space()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__disk_space_Class
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

    // 測試（唯一真實檢查動作——磁碟可用空間讀取（web 所在磁碟）；回原果）
    public function setup_test()
    {
        $root_dir = sc_get_config("storage.root_dir");

        $free_space_bytes = @disk_free_space($root_dir);

        // 判定：讀唔到 → 原果 warn（無法探測）
        if ($free_space_bytes === false) {
            return ["ok" => false, "status" => "warn", "hint" => "", "detail" => setup()->i18n()->get("check.permission.disk_space.detail_unreadable"), "reading" => ""];
        }

        $free_space_gb = round($free_space_bytes / 1024 / 1024 / 1024, 2);

        // 判定：1GB 或以上 → 原果 pass（示可用值）
        if ($free_space_gb >= 1) {
            $disk_space_available = setup()->i18n()->get("check.permission.disk_space.detail_available_prefix") . $free_space_gb . " GB";

            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => $disk_space_available, "reading" => ""];
        }

        // 其餘（少於 1GB）→ 原果 warn（提醒清理）
        $disk_space_low = setup()->i18n()->get("check.permission.disk_space.detail_low_prefix") . $free_space_gb . setup()->i18n()->get("check.permission.disk_space.detail_low_suffix");

        return ["ok" => false, "status" => "warn", "hint" => "", "detail" => $disk_space_low, "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    // 安裝步驟資料（標題／行值——框架步・檢查項：冇輸入值，行值留空）
    public function get_install_step_data()
    {
        return ["label" => setup()->i18n()->get("install.step.disk_space"), "value" => ""];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "warn";
        $check_result["label"] = setup()->i18n()->get("check.permission.disk_space.label");

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

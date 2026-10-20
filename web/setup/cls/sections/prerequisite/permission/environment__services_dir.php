<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：environment__services_dir.php（前置檢查「目錄 services（背景引擎所在）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：permission()->environment__services_dir()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class environment__services_dir_Class
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

    // 測試（唯一真實檢查動作——services 目錄存在？回原果）
    public function setup_test()
    {
        $services = sc_get_config("services");

        // 判定：存在 → 原果 pass（全路徑示位）
        if (is_dir($services)) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => $services . setup()->i18n()->get("check.permission.detail_exists"), "reading" => ""];
        }

        // 其餘（唔存在）→ 原果 fail（缺服務樹）
        return ["ok" => false, "status" => "fail", "hint" => setup()->i18n()->get("check.permission.services_dir.hint_position"), "detail" => $services . setup()->i18n()->get("check.permission.detail_missing"), "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    // 安裝步驟資料（標題／行值——框架步・檢查項：冇輸入值，行值留空）
    public function get_install_step_data()
    {
        return ["label" => setup()->i18n()->get("install.step.services_dir"), "value" => ""];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "warn";
        $check_result["label"] = setup()->i18n()->get("check.permission.services_dir.label");

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

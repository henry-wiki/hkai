<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：basic__php_version.php（前置檢查「PHP 版本（7.3 起可用；8.1 起 pass）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：basic()->php_version()（basic 域本體門）
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class basic__php_version_Class
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

    // 測試（唯一真實檢查動作——PHP 版本判定；回原果）
    public function setup_test()
    {
        $php_version_reading = PHP_VERSION;

        // 判定：低過 7.3 → 原果 fail（太舊唔支援）
        if (version_compare(PHP_VERSION, "7.3", "<")) {
            return ["ok" => false, "status" => "fail", "hint" => setup()->i18n()->get("check.basic.php_version.hint_too_old"), "detail" => "", "reading" => $php_version_reading];
        }

        // 判定：未到 8.1 → 原果 warn（可運行——未建議）
        if (version_compare(PHP_VERSION, "8.1", "<")) {
            return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.basic.php_version.hint_unsupported"), "detail" => "", "reading" => $php_version_reading];
        }

        // 其餘（8.1 或以上）→ 原果 pass（支援範圍內）
        return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $php_version_reading];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    public function setup_check()
    {
        $check_result = [];

        $test_result = $this->setup_test();
        $test_status = $test_result["status"];
        $test_hint   = $test_result["hint"];
        $test_detail = $test_result["detail"];
        $test_reading = $test_result["reading"];

        $check_result["status"] = $test_status;
        $label_prefix = setup()->i18n()->get("check.basic.php_version.label_prefix");
        $label_suffix = setup()->i18n()->get("check.basic.php_version.label_suffix");
        $check_item_label = $label_prefix . $test_reading . $label_suffix;
        $check_result["label"] = $check_item_label;

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

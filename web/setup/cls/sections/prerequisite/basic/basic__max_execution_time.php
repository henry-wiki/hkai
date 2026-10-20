<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：basic__max_execution_time.php（前置檢查「PHP 執行時限（建議 300 秒或以上）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：basic()->max_execution_time()（basic 域本體門）
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class basic__max_execution_time_Class
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

    // 測試（唯一真實檢查動作——執行時限判定；回原果）
    public function setup_test()
    {
        $max_execution_time_seconds = (int)ini_get("max_execution_time");

        // 判定：0＝不限 → 原果 pass
        if ($max_execution_time_seconds === 0) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => "" . $max_execution_time_seconds];
        }

        // 判定：300 秒或以上 → 原果 pass
        if ($max_execution_time_seconds >= 300) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => "" . $max_execution_time_seconds];
        }

        // 其餘（太少）→ 原果 warn（提醒——唔阻運行）
        $max_execution_detail = setup()->i18n()->get("check.basic.max_execution_time.detail_prefix") . $max_execution_time_seconds . setup()->i18n()->get("check.basic.max_execution_time.detail_suffix");

        return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.basic.max_execution_time.hint_warn"), "detail" => $max_execution_detail, "reading" => "" . $max_execution_time_seconds];
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
        $label_prefix = setup()->i18n()->get("check.basic.max_execution_time.label_prefix");
        $label_suffix = setup()->i18n()->get("check.basic.max_execution_time.label_suffix");
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

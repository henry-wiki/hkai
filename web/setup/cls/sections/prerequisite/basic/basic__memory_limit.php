<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：basic__memory_limit.php（前置檢查「PHP 記憶體上限（建議 128M 或以上）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：basic()->memory_limit()（basic 域本體門）
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class basic__memory_limit_Class
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

    // 測試（唯一真實檢查動作——記憶體上限判定；回原果）
    public function setup_test()
    {
        $memory_limit_text = ini_get("memory_limit");

        // 判定：冇限制（-1）→ 原果 pass
        if ($memory_limit_text === "-1") {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $memory_limit_text];
        }

        // 位元組換算（尾碼 G／M／K）
        $memory_limit_lower = trim($memory_limit_text);
        $memory_limit_lower = strtolower($memory_limit_lower);
        $memory_unit_char   = substr($memory_limit_lower, -1);
        $memory_number      = (int)$memory_limit_lower;
        $memory_bytes       = $memory_number;

        // 判定：單位 G → 乘 1024 三次
        if ($memory_unit_char === "g") {
            $memory_bytes = $memory_number * 1024 * 1024 * 1024;
        }

        // 判定：單位 M → 乘 1024 兩次
        if ($memory_unit_char === "m") {
            $memory_bytes = $memory_number * 1024 * 1024;
        }

        // 判定：單位 K → 乘 1024
        if ($memory_unit_char === "k") {
            $memory_bytes = $memory_number * 1024;
        }

        // 判定：128M 或以上 → 原果 pass
        if ($memory_bytes >= 128 * 1024 * 1024) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $memory_limit_text];
        }

        // 其餘（低過 128M）→ 原果 warn（提醒——唔阻運行）
        $memory_limit_detail = setup()->i18n()->get("check.basic.memory_limit.detail_prefix") . $memory_limit_text . setup()->i18n()->get("check.basic.memory_limit.detail_suffix");

        return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.basic.memory_limit.hint_warn"), "detail" => $memory_limit_detail, "reading" => $memory_limit_text];
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
        $label_prefix = setup()->i18n()->get("check.basic.memory_limit.label_prefix");
        $label_suffix = setup()->i18n()->get("check.basic.memory_limit.label_suffix");
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

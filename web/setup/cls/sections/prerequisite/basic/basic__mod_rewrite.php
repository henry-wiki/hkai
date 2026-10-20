<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：basic__mod_rewrite.php（前置檢查「重寫模組 mod_rewrite（路由分派）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：basic()->mod_rewrite()（basic 域本體門）
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class basic__mod_rewrite_Class
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

    // 測試（唯一真實檢查動作——重寫模組判定〔Apache 專用查法；mini server 由 router script 分派〕；回原果）
    public function setup_test()
    {
        $current_sapi_name = php_sapi_name();

        // 判定：mini server（php -S——真實運行環境）→ 原果 pass（跳過此驗證——路由由 router script 分派）
        if ($current_sapi_name === "cli-server") {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => setup()->i18n()->get("check.basic.mod_rewrite.detail_mini_server"), "reading" => ""];
        }

        // 判定：唔係 Apache SAPI（查唔到模組清單）→ 原果 warn（自行確保）
        if (!function_exists("apache_get_modules")) {
            return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.basic.mod_rewrite.hint_not_apache"), "detail" => "", "reading" => ""];
        }

        // 判定：Apache 但模組清單冇 mod_rewrite → 原果 fail
        $apache_modules = apache_get_modules();

        if (!in_array("mod_rewrite", $apache_modules)) {
            return ["ok" => false, "status" => "fail", "hint" => setup()->i18n()->get("check.basic.mod_rewrite.hint_disabled"), "detail" => "", "reading" => ""];
        }

        // 其餘 → 原果 pass（已載入）
        return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    public function setup_check()
    {
        $check_result = [];
        $check_result["label"] = setup()->i18n()->get("check.basic.mod_rewrite.label");

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

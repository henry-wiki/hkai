<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：php_extension__flock.php（前置檢查「函數 flock（隊列檔鎖）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：php_extension()->php_extension__flock()
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class php_extension__flock_Class
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

    // 測試（唯一真實檢查動作——函數可用？回原果）
    public function setup_test()
    {
        // 判定：函數可用（存在＋未停用）→ 原果 pass
        if (function_exists("flock")) {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => ""];
        }

        // 其餘（唔存在或已停用）→ 原果 fail
        return ["ok" => false, "status" => "fail", "hint" => setup()->i18n()->get("check.php_function.hint_fail"), "detail" => "", "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    public function setup_check()
    {
        $check_result = [];
        $check_result["label"] = setup()->i18n()->get("check.php_function.flock.label");

        $test_result = $this->setup_test();
        $test_status = $test_result["status"];
        $test_hint   = $test_result["hint"];

        $check_result["status"] = $test_status;

        // 判定：原果帶 hint → 入對應格（force layout 用）
        if ($test_hint !== "") {
            $check_result["result_content"][$test_status]["hint"] = $test_hint;
        }

        return $check_result;
    }
}

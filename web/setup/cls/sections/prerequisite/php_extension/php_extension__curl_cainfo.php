<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：php_extension__curl_cainfo.php（前置檢查「curl 憑證設定（curl.cainfo）」——單項自足）
// == 職責：本項全部——setup_check()＝包裝（叫 setup_test → UI 格式）；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：php_extension()->curl_cainfo()（php_extension 域本體門）
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class php_extension__curl_cainfo_Class
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

    // 測試（唯一真實檢查動作——curl.cainfo 已設定且憑證檔在？回原果）
    public function setup_test()
    {
        $cainfo_path = ini_get("curl.cainfo");
        $cainfo_path = trim(strval($cainfo_path));

        // 判定：未設定（空白）→ 原果 fail
        if ($cainfo_path === "") {
            return ["ok" => false, "status" => "fail", "hint" => setup()->i18n()->get("check.php_extension.curl_cainfo.hint_fail"), "detail" => "", "reading" => ""];
        }

        // 判定：設定值指向的檔案不存在 → 原果 fail
        if (!is_file($cainfo_path)) {
            $cainfo_missing_hint = setup()->i18n()->get("check.php_extension.curl_cainfo.hint_missing") . $cainfo_path;

            return ["ok" => false, "status" => "fail", "hint" => $cainfo_missing_hint, "detail" => "", "reading" => ""];
        }

        // 其餘（已設定且憑證檔在）→ 原果 pass
        return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    public function setup_check()
    {
        $check_result = [];
        $check_result["label"] = setup()->i18n()->get("check.php_extension.curl_cainfo.label");

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


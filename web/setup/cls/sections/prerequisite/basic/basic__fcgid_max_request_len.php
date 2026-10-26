<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：basic__fcgid_max_request_len.php（前置檢查「FastCGI 上傳請求上限」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實動作（回原果）
// == 接入：basic()->fcgid_max_request_len()（basic 域本體門）
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class basic__fcgid_max_request_len_Class
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

    // 測試（Apache mod_fcgid 嘅 FcgidMaxRequestLen 冇 PHP API 可讀——只憑 SAPI 判適用性；回原果）
    public function setup_test()
    {
        $current_sapi_name = php_sapi_name();

        // 判定：唔係 php-cgi（cli／cli-server／apache2handler 等）→ 原果 pass（此限唔適用）
        if ($current_sapi_name !== "cgi-fcgi") {
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => setup()->i18n()->get("check.basic.fcgid_max_request_len.detail_not_applicable"), "reading" => ""];
        }

        // 其餘（php-cgi——mod_fcgid／php-fpm）→ 原果 warn（上限喺 Apache conf，PHP 讀唔到）
        return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.basic.fcgid_max_request_len.hint_unknown"), "detail" => "", "reading" => ""];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式）
    public function setup_check()
    {
        $check_result = [];
        $check_result["label"] = setup()->i18n()->get("check.basic.fcgid_max_request_len.label");

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

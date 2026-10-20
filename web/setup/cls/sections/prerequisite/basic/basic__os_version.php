<?php

namespace sc\setup\sections\prerequisite;

// == HKAI setup 檢查單元：basic__os_version.php（前置檢查「作業系統（Windows 10+／Linux／macOS）」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔叫 setup_test → UI 格式〕；setup_test()＝唯一真實檢查動作（回原果）
// == 接入：basic()->os_version()（basic 域本體門）
// == 載入：顯式（無 autoloader）——prerequisite 格檢查橋按需 require 本檔

class basic__os_version_Class
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

    // 測試（唯一真實檢查動作——作業系統判定；回原果）
    public function setup_test()
    {
        $os_family  = PHP_OS_FAMILY;
        $os_release = php_uname("r");

        $os_label = $os_family . "（" . $os_release . "）";

        // 判定：Windows → 認版本（10.0＝10／11；6.3＝8.1；6.2＝8；6.1＝7）
        if ($os_family === "Windows") {
            $release_parts = explode(".", $os_release);

            $major_version = $release_parts[0];

            // 判定：有次版號 → 併成主版（10.0／6.1）
            if (isset($release_parts[1])) {
                $major_version = $release_parts[0] . "." . $release_parts[1];
            }

            $windows_names = [
                "10.0" => "Windows 10／11",
                "6.3"  => "Windows 8.1",
                "6.2"  => "Windows 8",
                "6.1"  => "Windows 7",
                "6.0"  => "Windows Vista",
                "5.1"  => "Windows XP",
            ];

            $os_label = "Windows " . $os_release;

            // 判定：名表有 → 用友善名
            if (isset($windows_names[$major_version])) {
                $os_label = $windows_names[$major_version];
            }

            // 判定：舊過 Windows 10 → 原果 warn
            if (version_compare($major_version, "10.0", "<")) {
                return ["ok" => false, "status" => "warn", "hint" => setup()->i18n()->get("check.basic.os_version.hint_old_system"), "detail" => "", "reading" => $os_label];
            }

            // 其餘 → 原果 pass（支援）
            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $os_label];
        }

        // 判定：Linux → 原果 pass（核心版本）
        if ($os_family === "Linux") {
            $label_prefix = setup()->i18n()->get("check.basic.os_version.linux_label_prefix");
        $label_suffix = setup()->i18n()->get("check.basic.os_version.linux_label_suffix");
        $os_label = $label_prefix . $os_release . $label_suffix;

            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $os_label];
        }

        // 判定：Darwin（macOS）→ 原果 pass
        if ($os_family === "Darwin") {
            $label_prefix = setup()->i18n()->get("check.basic.os_version.macos_label_prefix");
        $label_suffix = setup()->i18n()->get("check.basic.os_version.macos_label_suffix");
        $os_label = $label_prefix . $os_release . $label_suffix;

            return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $os_label];
        }

        // 其餘 → 原果 pass（未知系統——照讀）
        return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $os_label];
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
        $check_result["label"] = $test_reading;

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

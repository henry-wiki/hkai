<?php

namespace sc\setup\sections\setting;

// == HKAI setup 檢查單元：setting.timezone.php（設定格「時區」——單項自足）
// == 職責：呢一項嘅全部——setup_check()＝包裝〔來源鏈：記錄 → .env → 瀏覽器（請求頭）→ 空〕；setup_test()＝後端源（.env → 瀏覽器——回原果）
// == 接入：setting 格檢查表（closure）＋sections 域派發（get_section_unit_instance("settings", "initialization__timezone")）
// == 載入：顯式（無 autoloader）——setting 域門（setting.func.php::timezone()）按需 require 本檔

class setting_timezone_Class
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

    // 測試（後端源取得〔.env → 瀏覽器（請求頭）〕；回原果）
    public function setup_test()
    {
        // f1：.env 取得（APP_TIMEZONE）
        $timezone_value = trim(sc_get_env("APP_TIMEZONE", ""));

        // f2：瀏覽器取得（前端隨每個請求帶返嘅時區頭）
        if ($timezone_value === "") {
            $timezone_browser_value = "";

            // 判定：請求帶咗時區頭 → 取值
            if (isset($_SERVER["HTTP_X_BROWSER_TIMEZONE"])) {
                $timezone_browser_value = trim($_SERVER["HTTP_X_BROWSER_TIMEZONE"]);
            }

            // 判定：係時區資料庫內嘅名（外部輸入——防亂值）→ 取用
            if (in_array($timezone_browser_value, \DateTimeZone::listIdentifiers())) {
                $timezone_value = $timezone_browser_value;
            }
        }

        // 其餘（全部未取得）→ 原果 pass（空值——選單佔位「請選擇時區」等用戶自選）
        return ["ok" => true, "status" => "pass", "hint" => "", "detail" => "", "reading" => $timezone_value];
    }

    // 檢查（包裝層——零檢查碼：叫 setup_test 原果 → UI 格式；來源鏈：記錄 → .env → 瀏覽器 → 空）
    // 安裝步驟資料（標題／行值——值由本單元自決：記錄值／未填＝留空字眼）
    public function get_install_step_data()
    {
        $timezone_recorded_value = setup()->state()->get_setting_setup_process("settings", "initialization__timezone");

        $install_step_value = $timezone_recorded_value;

        // 判定：未填（空）→ 留空字眼
        if ($install_step_value === "") {
            $install_step_value = setup()->i18n()->get("install.value_empty");
        }

        return ["label" => setup()->i18n()->get("install.step.timezone"), "value" => $install_step_value];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["label"] = setup()->i18n()->get("check.setting.timezone.label");

        $test_result  = $this->setup_test();
        $test_reading = $test_result["reading"];

        // 記錄值（setup_process.json——用戶已揀嘅時區＝第一源；冇記錄＝空串）
        $timezone_recorded_value = setup()->state()->get_setting_setup_process("settings", "initialization__timezone");

        // 控制值（來源鏈：記錄 → .env → 瀏覽器 → 空）
        $timezone_control_value = $test_reading;

        // 判定：有記錄 → 記錄優先
        if ($timezone_recorded_value !== "") {
            $timezone_control_value = $timezone_recorded_value;
        }

        $timezone_options = \DateTimeZone::listIdentifiers();

        // 佔位（空值——「請選擇時區」；均不可得時選中佢，唔會自動揀第一個）
        array_unshift($timezone_options, ["value" => "", "label" => setup()->i18n()->get("check.setting.timezone.select_placeholder")]);

        // 控制組＝時區選單（全狀態常駐——選項全部時區）
        $check_result["input"] = [
            "name"    => "initialization__timezone",
            "value"   => $timezone_control_value,
            "options" => $timezone_options,
        ];

        // 判定：有取得（記錄／.env／瀏覽器）→ pass；均不可得（空值）→ warn（等用戶自選）
        if ($timezone_control_value !== "") {
            $check_result["status"] = "pass";
        } else {
            $check_result["status"] = "warn";
        }

        return $check_result;
    }

    // 安裝（未設定＝取時區〔記錄 → .env → 瀏覽器〕填 .env；已有＝保留；取唔到＝跳過——回動作原果）
    public function setup_install()
    {
        $env_timezone = trim(sc_get_env("APP_TIMEZONE", ""));

        // 判定：.env 已有值 → 唔覆蓋（保留）
        if ($env_timezone !== "") {
            return ["ok" => true, "result" => "kept", "detail" => setup()->i18n()->get("install.detail.kept")];
        }

        // 攞值（來源鏈：記錄 → .env → 瀏覽器——同檢查同一機制）
        $timezone_recorded_value = setup()->state()->get_setting_setup_process("settings", "initialization__timezone");

        $timezone_value = "";

        // 判定：有記錄 → 記錄優先（用戶已揀）
        if ($timezone_recorded_value !== "") {
            $timezone_value = $timezone_recorded_value;
        }

        // 判定：冇記錄 → 走後端源（.env → 瀏覽器）
        if ($timezone_value === "") {
            $test_result    = $this->setup_test();
            $timezone_value = $test_result["reading"];
        }

        // 判定：冇值可取（三者均空）→ 跳過（唔算失敗——用戶未揀）
        if ($timezone_value === "") {
            return ["ok" => true, "result" => "skipped", "detail" => setup()->i18n()->get("install.detail.timezone_skipped")];
        }

        // 寫 .env（sc_set_env——缺先加·已有唔覆蓋）
        sc_set_env("APP_TIMEZONE", $timezone_value);

        return ["ok" => true, "result" => "written", "detail" => setup()->i18n()->get("install.detail.written")];
    }
}

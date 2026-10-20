<?php

namespace sc\setup\sections\setting;

// == HKAI setup 檢查單元：provider__openrouter.php（設定格「OpenRouter API key」——單項自足；永遠通過〔選填輸入位〕；安裝時把打過嘅值寫 .env）
// == 職責：呢一項嘅全部——setup_check()＝UI 格式（永 pass＋輸入位）；setup_install()＝安裝段讀寫
// == 接入：setting 域門——provider()->openrouter()（按需 require 本檔；檢查表＋sections 域派發用）
// == 載入：顯式（無 autoloader）——provider 域門按需 require 本檔

class provider__openrouter_Class
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

    // 檢查（永遠通過——選填輸入位；回填＝解密後真值（有 encrypt_content 即解返出嚟——供覆核／修改）·.env 值永不顯示）
    // 安裝步驟資料（標題／行值——值由本單元自決：解密真值遮罩／未填＝留空字眼）
    public function get_install_step_data()
    {
        $openrouter_recorded_value = setup()->state()->get_sensitive_setting_setup_process("settings", "key_settings__openrouter");

        $install_step_value = "";

        // 判定：已填 → 遮罩（前十八星＋尾四——明文取尾）；未填 → 留空字眼
        if ($openrouter_recorded_value !== "") {
            $install_step_value = str_repeat("*", 18) . substr($openrouter_recorded_value, -4);
        } else {
            $install_step_value = setup()->i18n()->get("install.value_empty");
        }

        return ["label" => setup()->i18n()->get("install.step.openrouter"), "value" => $install_step_value];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "pass";
        $check_result["label"] = setup()->i18n()->get("check.setting.openrouter.label");

        $openrouter_recorded_value = setup()->state()->get_sensitive_setting_setup_process("settings", "key_settings__openrouter");

        $check_result["input"] = [
            "name"        => "key_settings__openrouter",
            "value"       => $openrouter_recorded_value,
            "placeholder" => setup()->i18n()->get("check.setting.placeholder_api_key"),
        ];

        return $check_result;
    }

    // 安裝（用戶打過嘅 key 解密後寫入 .env——缺先加·已有唔覆蓋；未打＝跳過）
    public function setup_install()
    {
        $openrouter_recorded_value = setup()->state()->get_sensitive_setting_setup_process("settings", "key_settings__openrouter");

        // 判定：未打 → 跳過（選填——唔算失敗）
        if ($openrouter_recorded_value === "") {
            return ["ok" => true, "result" => "skipped", "detail" => setup()->i18n()->get("install.detail.key_not_filled_skipped")];
        }

        // 寫 .env（sc_set_env——缺先加·已有唔覆蓋）
        sc_set_env("OPENROUTER_API_KEY", $openrouter_recorded_value);

        return ["ok" => true, "result" => "written", "detail" => setup()->i18n()->get("install.detail.written")];
    }
}

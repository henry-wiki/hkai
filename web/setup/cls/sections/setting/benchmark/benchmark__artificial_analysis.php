<?php

namespace sc\setup\sections\setting;

// == HKAI setup 檢查單元：benchmark__artificial_analysis.php（設定格「Artificial Analysis API key（選填）」——單項自足；永遠通過〔選填輸入位〕；安裝時把打過嘅值寫 .env）
// == 職責：呢一項嘅全部——setup_check()＝UI 格式（永 pass＋輸入位＋資料源說明）；setup_install()＝安裝段讀寫
// == 接入：setting 域門——benchmark()->artificial_analysis()（按需 require 本檔；檢查表＋sections 域派發用）
// == 載入：顯式（無 autoloader）——benchmark 域門按需 require 本檔

class benchmark__artificial_analysis_Class
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

    // 檢查（永遠通過——選填輸入位；值＝記錄優先——用戶打過嗰個·.env 值永不顯示；pass detail＝資料源說明〔卡片 cdesc〕）
    // 安裝步驟資料（標題／行值——值由本單元自決：解密真值遮罩／未填＝留空字眼）
    public function get_install_step_data()
    {
        $artificial_analysis_recorded_value = setup()->state()->get_sensitive_setting_setup_process("settings", "key_settings__artificial_analysis");

        $install_step_value = "";

        // 判定：已填 → 遮罩（前十八星＋尾四——明文取尾）；未填 → 留空字眼
        if ($artificial_analysis_recorded_value !== "") {
            $install_step_value = str_repeat("*", 18) . substr($artificial_analysis_recorded_value, -4);
        } else {
            $install_step_value = setup()->i18n()->get("install.value_empty");
        }

        return ["label" => setup()->i18n()->get("install.step.artificial_analysis"), "value" => $install_step_value];
    }

    public function setup_check()
    {
        $check_result = [];
        $check_result["status"] = "pass";
        $check_result["label"] = setup()->i18n()->get("check.setting.artificial_analysis.label");
        $check_result["result_content"]["pass"]["detail"] = setup()->i18n()->get("check.setting.artificial_analysis.detail_pass");

        $artificial_analysis_recorded_value = setup()->state()->get_sensitive_setting_setup_process("settings", "key_settings__artificial_analysis");

        $check_result["input"] = [
            "name"        => "key_settings__artificial_analysis",
            "value"       => $artificial_analysis_recorded_value,
            "placeholder" => setup()->i18n()->get("check.setting.placeholder_api_key"),
        ];

        return $check_result;
    }

    // 安裝（用戶打過嘅 key 解密後寫入 .env——缺先加·已有唔覆蓋；未打＝跳過）
    public function setup_install()
    {
        $artificial_analysis_recorded_value = setup()->state()->get_sensitive_setting_setup_process("settings", "key_settings__artificial_analysis");

        // 判定：未打 → 跳過（選填——唔算失敗）
        if ($artificial_analysis_recorded_value === "") {
            return ["ok" => true, "result" => "skipped", "detail" => setup()->i18n()->get("install.detail.key_not_filled_skipped")];
        }

        // 寫 .env（sc_set_env——缺先加·已有唔覆蓋）
        sc_set_env("BENCHMARK_SRC_AA_API_KEY", $artificial_analysis_recorded_value);

        return ["ok" => true, "result" => "written", "detail" => setup()->i18n()->get("install.detail.written")];
    }
}

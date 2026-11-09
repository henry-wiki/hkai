<?php

namespace sc\setup\sections;

// == HKAI setup sections welcome.install：welcome 格嘅 install 側（trait——掛入 welcome_Class；檔＝本格嘅安裝動作）
// == 內容：安裝制方法＝get_install_step_data／setup_install（寫入行 sc_save_settings——機制同 timezone）
// == 載入：welcome.php require_once（include once）

trait welcome_install
{
    // 安裝步驟資料（標題／行值——值由本單元自決：記錄值／未填＝留空字眼）
    public function get_install_step_data()
    {
        $language_recorded_value = setup()->state()->get_setting_setup_process("welcome", "language");

        $install_step_value = $language_recorded_value;

        // 判定：未填（空）→ 留空字眼
        if ($install_step_value === "") {
            $install_step_value = setup()->i18n()->get("install.value_empty");
        }

        return ["label" => setup()->i18n()->get("install.step.language"), "value" => $install_step_value];
    }

    // 安裝（把語言記錄寫入應用設定——回動作原果）
    public function setup_install()
    {
        $language_record_value = setup()->state()->get_setting_setup_process("welcome", "language");

        $language_settings = [];
        $language_settings["general"] = [];
        $language_settings["general"]["language"] = $language_record_value;

        // 寫設定檔（<data>/config/settings.json——合併寫，機制同 timezone）
        $write_result = sc_save_settings($language_settings);

        // 判定：寫入失敗 → fail
        if ($write_result !== true) {
            return ["ok" => false, "result" => "failed", "detail" => setup()->i18n()->get("install.detail.language_write_failed")];
        }

        return ["ok" => true, "result" => "written", "detail" => setup()->i18n()->get("install.detail.language_written")];
    }
}

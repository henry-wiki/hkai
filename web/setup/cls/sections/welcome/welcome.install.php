<?php

namespace sc\setup\sections;

// == HKAI setup sections welcome.install：welcome 格嘅 install 側（trait——掛入 welcome_Class；檔＝本格嘅安裝動作）
// == 內容：安裝制方法＝get_install_step_data／setup_install（私助＝read_app_settings／write_app_settings／get_app_settings_file）
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
        $settings_data = $this->read_app_settings();

        $language_record_value = setup()->state()->get_setting_setup_process("welcome", "language");

        // 判定：應用設定未有 general 組 → 先立（空）
        if (!isset($settings_data["general"])) {
            $settings_data["general"] = [];
        }

        $settings_data["general"]["language"] = $language_record_value;

        $write_result = $this->write_app_settings($settings_data);

        // 判定：寫入失敗 → fail
        if ($write_result !== true) {
            return ["ok" => false, "result" => "failed", "detail" => setup()->i18n()->get("install.detail.language_write_failed")];
        }

        return ["ok" => true, "result" => "written", "detail" => setup()->i18n()->get("install.detail.language_written")];
    }

    // 讀應用設定（settings.json——唔存在／唔合法＝空陣列）
    private function read_app_settings()
    {
        $app_settings_file = $this->get_app_settings_file();

        // 判定：設定檔唔存在 → 空
        if (!is_file($app_settings_file)) {
            return [];
        }

        $settings_raw = @file_get_contents($app_settings_file);

        // 判定：讀唔到 → 空
        if ($settings_raw === false) {
            return [];
        }

        $settings_decoded = json_decode($settings_raw, true);

        // 判定：解析唔到陣列 → 空
        if (!is_array($settings_decoded)) {
            return [];
        }

        return $settings_decoded;
    }

    // 寫應用設定（settings.json——同 app hkai_core 同一格式；回成功唔成功）
    private function write_app_settings($settings_data)
    {
        $app_settings_file = $this->get_app_settings_file();

        $settings_json = json_encode($settings_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // 檔尾補換行（同現有檔格式）
        $write_result = @file_put_contents($app_settings_file, $settings_json . "\n", LOCK_EX);

        return ($write_result !== false);
    }

    // 應用設定檔路徑（sc_config/hkai/settings.json——單一來源＝sc_config/load.php）
    private function get_app_settings_file()
    {
        $config = sc_get_config("config");

        return $config["hkai_settings"];
    }
}

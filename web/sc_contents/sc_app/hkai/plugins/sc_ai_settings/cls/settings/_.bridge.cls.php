<?php

namespace sc\app\hkai\plugins\sc_ai_settings\settings;

// == sc_ai_settings settings：settings 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->settings()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat main_Class＋request_Class 合成） ==

class settings_Class
{
    use config\get_initial_data;
    use menu\handle_fetch_settings;

    private static $instance = null;

    private $initial_data = [];
    private $is_initial = 0;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }

    public function __construct()
    {}

    // initial data 組裝（第一次呼叫才執行——setting_sections：side menu section 清單）
    private function initial()
    {
        $setting_sections = [];
        // General（第一頁——業界慣例：語言／外觀等偏好集中此頁）
        $setting_sections["general"] = [
            "id"   => "general",
            "name" => hkai__i18n()->get("settings.section.general"),
        ];
        $setting_sections["instructions"] = [
            "id"       => "",
            "name"     => hkai__i18n()->get("settings.section.instructions"),
            "children" => [
                "system_prompt" => [
                    "id"   => "system_prompt",
                    "name" => hkai__i18n()->get("settings.section.system_prompt"),
                ],
                "system_env" => [
                    "id"   => "system_env",
                    "name" => hkai__i18n()->get("settings.section.system_env"),
                ],
            ],
        ];
        // Provider（模型 API 來源——一頁列全部 provider；子項唔開）
        $setting_sections["provider"] = [
            "id"   => "provider",
            "name" => hkai__i18n()->get("settings.section.provider"),
        ];
        $setting_sections["server"] = [
            "id"   => "server",
            "name" => hkai__i18n()->get("settings.section.server"),
        ];
        $setting_sections["process_viewer"] = [
            "id"   => "process_viewer",
            "name" => hkai__i18n()->get("settings.section.process_viewer"),
        ];
        // 關於 HKAI（分頁——同其餘 section 一樣只換右欄內容；左選單常在）
        $setting_sections["about"] = [
            "id"   => "about",
            "name" => hkai__i18n()->get("settings.about.title"),
        ];

        $initial_data = [];
        $initial_data["setting_sections"] = $setting_sections;

        $this->initial_data = $initial_data;
    }

    // 單一 section 的選單 HTML（有 children → 父項＋子項容器；否則單一按鈕）
    private function build_nav_section_html($setting_item)
    {
        // 判定：2 層（父項 ＋ 子項）
        if (isset($setting_item["children"])) {
            $output_lines = [];
            $output_lines[] = "<button class='settings_nav_button settings_nav_parent' data-nav_toggle='1'>" . $setting_item["name"] . "</button>";
            $output_lines[] = "<div class='settings_nav_children'>";

                // 逐子項生成按鈕
                foreach ($setting_item["children"] as $child_data_item) {
                    $output_lines[] = "<button class='settings_nav_button' data-section='" . $child_data_item["id"] . "'>" . $child_data_item["name"] . "</button>";
                }

            $output_lines[] = "</div>";

            return implode("\r\n", $output_lines);
        }

        return "<button class='settings_nav_button' data-section='" . $setting_item["id"] . "'>" . $setting_item["name"] . "</button>";
    }
}

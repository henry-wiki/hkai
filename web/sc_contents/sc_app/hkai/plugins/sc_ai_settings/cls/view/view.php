<?php

namespace sc\app\hkai\plugins\sc_ai_settings\view;

// == sc_ai_settings view：設定對話框本體（側選單＋section 清單；singleton——呼叫形 sc_ai_settings()->view()） ==
// == 上游：request/settings/fetch_settings.php（GET 入口）；下游：hkai()->i18n()（section 名稱）、sc_response()->json()
// == 方法：handle_fetch_settings（入口）／get_initial_data／initial（section 定義）／build_nav_section_html（選單 HTML）

class view_Class
{
    private static $instance = null;

    // initial data（第一次呼叫才組裝）
    private $initial_data = [];
    private $is_initial = 0;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }

        return self::$instance;
    }

    public function __construct()
    {}

    // 取得 initial data（第一次呼叫才執行 initial；$idx null → 全部）
    public function get_initial_data($idx = null)
    {
        // 判定：未組裝 → 先組裝
        if (!$this->is_initial) {
            $this->is_initial = 1;
            $this->initial();
        }

        // 判定：無指定 idx → 回全部
        if ($idx === null) {
            return $this->initial_data;
        }

        return $this->initial_data[$idx];
    }

    // GET fetch_settings handler（side menu 動態載入——回 initial data；直接 return json）
    public function handle_fetch_settings()
    {
        $setting_sections = $this->get_initial_data("setting_sections");
        $navigation       = [];

        $navigation[] = "<div class='modal__search_wrap'>";
            $navigation[] = "<i class='fa-solid fa-magnifying-glass modal__search_icon'></i>";
            $navigation[] = "<input type='text' class='modal__search_input' placeholder='" . hkai()->i18n()->get("settings.search") . "' />";
        $navigation[] = "</div>";
        $navigation[] = "<div class='modal__navigation-content'>";

            // 逐個 section 生成選單 HTML
            foreach ($setting_sections as $setting_item) {
                $navigation[] = $this->build_nav_section_html($setting_item);
            }

        $navigation[] = "</div>";

        $html = ["navigation" => implode("\r\n", $navigation)];

        return sc_response()->json(["ok" => true, "setting_sections" => $setting_sections, "html" => $html]);
    }

    // initial data 組裝（setting_sections：side menu section 清單）
    private function initial()
    {
        $setting_sections = [];

        // General（第一頁——業界慣例：語言／外觀等偏好集中此頁）
        $setting_sections["general"] = [
            "id"   => "general",
            "name" => hkai()->i18n()->get("settings.section.general"),
        ];

        // Instructions（父項＋兩子項：system_prompt／system_env）
        $setting_sections["instructions"] = [
            "id"       => "",
            "name"     => hkai()->i18n()->get("settings.section.instructions"),
            "children" => [
                "system_prompt" => [
                    "id"   => "system_prompt",
                    "name" => hkai()->i18n()->get("settings.section.system_prompt"),
                ],
                "system_env" => [
                    "id"   => "system_env",
                    "name" => hkai()->i18n()->get("settings.section.system_env"),
                ],
            ],
        ];

        // Provider（模型 API 來源——一頁列全部 provider；子項唔開）
        $setting_sections["provider"] = [
            "id"   => "provider",
            "name" => hkai()->i18n()->get("settings.section.provider"),
        ];

        $setting_sections["server"] = [
            "id"   => "server",
            "name" => hkai()->i18n()->get("settings.section.server"),
        ];

        $setting_sections["process_viewer"] = [
            "id"   => "process_viewer",
            "name" => hkai()->i18n()->get("settings.section.process_viewer"),
        ];

        // 關於 HKAI（分頁——同其餘 section 一樣只換右欄內容；左選單常在）
        $setting_sections["about"] = [
            "id"   => "about",
            "name" => hkai()->i18n()->get("settings.about.title"),
        ];

        $initial_data = [];
        $initial_data["setting_sections"] = $setting_sections;

        $this->initial_data = $initial_data;
    }

    // 單一 section 的選單 HTML（有 children → 父項＋子項容器；否則單一按鈕）
    private function build_nav_section_html($setting_item)
    {
        // 判定：2 層（父項＋子項）
        if (isset($setting_item["children"])) {
            $output_lines   = [];
            $output_lines[] = "<button class='settings_nav_button settings_nav_parent' data-nav_toggle='1'>" . $setting_item["name"] . "</button>";
            $output_lines[] = "<div class='settings_nav_children'>";

                // 逐個子項生成按鈕
                foreach ($setting_item["children"] as $child_data_item) {
                    $output_lines[] = "<button class='settings_nav_button' data-section='" . $child_data_item["id"] . "'>" . $child_data_item["name"] . "</button>";
                }

            $output_lines[] = "</div>";

            return implode("\r\n", $output_lines);
        }

        return "<button class='settings_nav_button' data-section='" . $setting_item["id"] . "'>" . $setting_item["name"] . "</button>";
    }
}

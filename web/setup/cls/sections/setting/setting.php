<?php

namespace sc\setup\sections;

// 短名域（setting 域單元——檢查表＋單元橋直取）
use sc\setup\sections\setting;

// 載入 setting 格內部短名（setting.func.php——setup() 向上接駁）
require_once __DIR__ . "/setting.func.php";

// 載入 provider 域內部短名（provider/_.internal.func.php——provider() 短名；include once）
require_once __DIR__ . "/provider/_.internal.func.php";

// 載入 benchmark 域內部短名（benchmark/_.internal.func.php——benchmark() 短名；include once）
require_once __DIR__ . "/benchmark/_.internal.func.php";

// == HKAI setup sections setting：設定格單元（格本體；呼叫方式 setup()->sections()->setting()）
// == 職責：本格整件（build_section＝head＋body＋footer——每格獨立決定）；檢查表內置；主題固定（靜態一行）

class setting_Class
{
    private static $instance = null;
    
    // 檢查表（what to check——key＝<單元名>（如 initialization__timezone／key_settings__openrouter）·值＝Closure（直呼檢查單元——F12 跳得到）；由 __construct 砌）
    private $check_items = [];

    // 分組表（group id => 檢查項 codes；次序＝介面次序）
    private $setting_groups = [];
    
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
        // 砌檢查表（一項一 Closure——直呼檢查單元·F12 跳得到；單元回 status（pass／fail／warn）＋label／detail／hint）
        $this->check_items = [];
        $this->check_items["initialization__timezone"] = function () {
            return setting\timezone()->setup_check();
        };
        
        $this->check_items["key_settings__deepseek"] = function () {
            return setting\provider()->deepseek()->setup_check();
        };

        $this->check_items["key_settings__openrouter"] = function () {
            return setting\provider()->openrouter()->setup_check();
        };

        $this->check_items["key_settings__kilocode"] = function () {
            return setting\provider()->kilocode()->setup_check();
        };

        $this->check_items["key_settings__llm_stats"] = function () {
            return setting\benchmark()->llm_stats()->setup_check();
        };

        $this->check_items["key_settings__artificial_analysis"] = function () {
            return setting\benchmark()->artificial_analysis()->setup_check();
        };

        // 分組（介面顯示次序＋組員——新增設定項只加一行；組別標題由 i18n 出）
        $this->setting_groups = [
            "general"   => ["initialization__timezone"],
            "model_api" => ["key_settings__deepseek", "key_settings__openrouter", "key_settings__kilocode"],
            "benchmark" => ["key_settings__llm_stats", "key_settings__artificial_analysis"],
        ];
        
    }
    
    // 出本格整件（head＋body＋footer——合成一個 array 交外殼；外殼只負責展示）
    public function build_section()
    {
        $section_nav = setup()->sections()->get_section_nav("settings");
        
        $section_lines   = [];
        $section_head_text = setup()->i18n()->get("section.setting.head");

        $section_lines[] = "<div class='setup_section-head'>" . $section_head_text . "</div>";
        
        $body_lines = $this->build_body();
        
        // 逐行入列（一行一動作——統一 $section_lines[] 形）
        foreach ($body_lines as $body_line_item) {
            $section_lines[] = $body_line_item;
        }
        
        $footer_lines = $this->build_footer($section_nav);
        
        // 逐行入列（同上）
        foreach ($footer_lines as $footer_line_item) {
            $section_lines[] = $footer_line_item;
        }
        
        return $section_lines;
    }
    
    // 出本格 body（區塊＋引言＋逐項卡＋主題靜態行——本格自己跑檢查表）
    public function build_body()
    {
        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-main'>";

            // 逐組（標題＋組員殼——組員次序照 setting_groups）
            foreach ($this->setting_groups as $setting_group_id => $setting_group_codes) {
                $group_title_text = setup()->i18n()->get("setting.group." . $setting_group_id);

                $section_lines[] = "<div class='setup_group-head'>" . $group_title_text . "</div>";

                // 逐個組員出殼（init 態——唔跑檢查；交前端逐項查、換真卡）
                foreach ($setting_group_codes as $check_item_code_item) {
                    $section_lines[] = setup()->view()->get_check_item_template($check_item_code_item);
                }

                // 判定：general 組 → 主題靜態行（固定預設——無檢查）
                if ($setting_group_id === "general") {
                    $section_lines[] = "<div class='check_item pass' data-status='pass'>";
                    $section_lines[] = "<div class='mark'>✓</div>";
                    $section_lines[] = "<div class='cinfo'>";
                    $theme_name_text = setup()->i18n()->get("section.setting.theme_name");
                    $theme_desc_text = setup()->i18n()->get("section.setting.theme_desc");

                    $section_lines[] = "<div class='cname'>" . $theme_name_text . "</div>";
                    $section_lines[] = "<div class='cdesc'>" . $theme_desc_text . "</div>";
                    $section_lines[] = "</div>";
                    $section_lines[] = "</div>";
                }
            }

        $section_lines[] = "</div>";

        return $section_lines;
    }

    public function build_footer($section_nav)
    {
        $prev_section_id = $section_nav["prev_section_id"];
        $next_section_id = $section_nav["next_section_id"];

        $section_lines   = [];
        $section_lines[] = "<div class='setup_section-foot'>";
        $section_lines[] = "<div class='setup_section-foot__left'>";
            $recheck_text = setup()->i18n()->get("common.recheck");
            $checking_text = setup()->i18n()->get("common.checking");

            $section_lines[] = "<button type='button' class='button' data-setup_nav='settings' data-nav_action='refresh'>";
            $section_lines[] = "<span class='setup_foot_refresh_idle'>" . $recheck_text . "</span>";
            $section_lines[] = "<span class='setup_foot_refresh_loading'>" . $checking_text . "</span>";
            $section_lines[] = "</button>";
            $section_lines[] = "<span class='check_counts'></span>";
        $section_lines[] = "</div>";

        $section_lines[] = "<div class='setup_section-foot__right'>";

            $prev_step_text = setup()->i18n()->get("common.prev_step");
            $next_step_text = setup()->i18n()->get("common.next_step");

            // 判定：第一格 → 上一步停用
            if ($section_nav["is_first"]) {
                $section_lines[] = "<button type='button' class='button' data-setup_nav='" . $prev_section_id . "' data-nav_action='prev' disabled>" . $prev_step_text . "</button>";
            }

            // 判定：唔係第一格 → 正常上一步
            if (!$section_nav["is_first"]) {
                $section_lines[] = "<button type='button' class='button' data-setup_nav='" . $prev_section_id . "' data-nav_action='prev'>" . $prev_step_text . "</button>";
            }

            // 判定：唔係最後一格 → 出下一步
            if (!$section_nav["is_last"]) {
                $section_lines[] = "<button type='button' class='button button--primary' data-setup_nav='" . $next_section_id . "' data-nav_action='next'>" . $next_step_text . "</button>";
            }

        $section_lines[] = "</div>";
        $section_lines[] = "</div>";

        return $section_lines;
    }

    // 跑單一檢查項（按 code 叫返對應檢查單元——跑咗回檢查結果；未知＝null）
    public function run_check_item($check_item_code)
    {
        // 判定：表內有此項 → 跑佢（closure＝唯一真實檢查入口）
        if (isset($this->check_items[$check_item_code])) {
            return $this->check_items[$check_item_code]();
        }

        return null;
    }

    public function get_state_value($check_item_code='',$value='')
    {
        if($check_item_code === "")
        {
            // == invalid check item code
            return ;
        }

        $state_value = [];
        $state_value["value"] = "";
        $state_value["update_at"] = date("Y-m-d H:i:s");

        if($check_item_code === "initialization__timezone")
        {
            $state_value["value"] = $value;
        }
        else if($check_item_code === "key_settings__openrouter")
        {
            // 敏感項——未改唔重寫；有改＝遮罩＋密文
            $state_value = $this->build_sensitive_key_state_value($check_item_code, $value);
        }
        else if($check_item_code === "key_settings__kilocode")
        {
            // 敏感項——未改唔重寫；有改＝遮罩＋密文
            $state_value = $this->build_sensitive_key_state_value($check_item_code, $value);
        }
        else if($check_item_code === "key_settings__llm_stats")
        {
            // 敏感項——未改唔重寫；有改＝遮罩＋密文
            $state_value = $this->build_sensitive_key_state_value($check_item_code, $value);
        }
        else if($check_item_code === "key_settings__artificial_analysis")
        {
            // 敏感項——未改唔重寫；有改＝遮罩＋密文
            $state_value = $this->build_sensitive_key_state_value($check_item_code, $value);
        }

        // 判定：敏感項未改（回 null）→ 唔寫（保留原記錄）
        if($state_value === null)
        {
            return null;
        }

        return $state_value;
    }

    // 私助：敏感項狀態值（讀現記錄明文——未改＝null〔唔重寫〕；有改＝砌遮罩＋密文記錄）
    private function build_sensitive_key_state_value($check_item_code, $value)
    {
        $existing_plain_value = setup()->state()->get_sensitive_setting_setup_process("settings", $check_item_code);

        // 判定：用戶未改（＝解密後原值）→ 唔重寫（回 null）
        if($existing_plain_value === $value)
        {
            return null;
        }

        return setup()->state()->build_sensitive_setting_entry($value);
    }
}

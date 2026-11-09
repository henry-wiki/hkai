<?php
namespace sc\config;

// == sc_config：config 檔集合載入器與讀寫（對應 Laravel Illuminate\Config\Repository） ==
// == 位置：web/sc_config/cls/（域 cls 根——與 config 檔同區；2026-10-12 自框架 foundation/config 搬入） ==
// == init_configs()：掃描值檔目錄（web/sc_config/assets/）→ latest_configs（基底） ==
// == init_settings()：讀 settings.json → latest_settings＋套用環境副作用（時區） ==
// == get_config()/set_config()：點分隔 chain → 逐層掃 latest_configs（sc_get_config('storage.database_file')） ==

class sc_config_Class
{
    private static $instance = null;

    private $latest_configs = array();

    private $latest_settings = array();
    private $loaded = false;
    
    public static function getInstance()
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    // 載入全部 config 檔（load.php 入口呼叫一次——對應 Laravel LoadConfiguration；config 檔目錄在應用層）
    public function init_configs()
    {
        // 已載入 → 跳過（載入一次即可；掃描中重入＝亦即刻返）
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        
        // config 值檔目錄（web/sc_config/assets/——上層推導；唔靠 SC_* 常數：常數由本倉派生，初始載入時尚未定義）
        $config_asset_files = glob(dirname(__DIR__) . "/assets/*.php");
        
        foreach ($config_asset_files as $config_asset_file_item) {
            $file_name = basename($config_asset_file_item, ".php");
            
            // require_once——防重複載入
            $asset_configs = require_once $config_asset_file_item;
            
            // 非 array（define 檔／已載入回 true）→ 跳過；array 檔以檔名為 key 深層合併
            if (!is_array($asset_configs)) {
                continue;
            }
            
            // 值檔以檔名為 key 併入（單一合併入口）
            $asset_config_group = array();
            $asset_config_group[$file_name] = $asset_configs;
            $this->merge_configs($asset_config_group);
        }
    }
    
    // 合併配置（大陣列一次過——新 key 加入、重複 key 覆蓋；只動記憶體）
    public function merge_configs($configs)
    {
        // 判定：唔係陣列 → 拒絕
        if (is_array($configs) === false) {
            return false;
        }

        $this->latest_configs = array_replace_recursive($this->latest_configs, $configs);

        return true;
    }
    
    // 初始化設定（載入 settings.json → latest_settings；套用時區——只做環境副作用）
    public function init_settings()
    {
        // 設定檔（<data>/config/settings.json）→ latest_settings
        $settings_file = sc_data_path()->get_data_sub_dir("config/settings.json");
        $settings_value = sc_read_json($settings_file);

        // 判定：缺檔／壞檔（null）→ 空表
        if ($settings_value === null) {
            $settings_value = array();
        }

        $this->merge_settings($settings_value);

        // --------------------------------------------------------------------------------------------------
        // 分界：上＝載入設定（settings.json → latest_settings）；下＝套用設定（設定值落到環境：時區）
        // --------------------------------------------------------------------------------------------------
        $app_timezone = $this->get_setting("general.timezone", "");

        // 判定：空值 → 用預設
        if ($app_timezone === "") {
            $app_timezone = "Asia/Hong_Kong";
        }

        date_default_timezone_set($app_timezone);
    }

    // 讀用戶設定（latest_settings——key＝group.name 或單層 key）
    public function get_setting($setting_chain, $default = null)
    {
        $setting_chain_parts = explode(".", $setting_chain);
        $setting_tree = $this->latest_settings;

        foreach ($setting_chain_parts as $setting_chain_part) {
            // key 不存在 → 回 default
            if (!isset($setting_tree[$setting_chain_part])) {
                return $default;
            }

            $setting_tree = $setting_tree[$setting_chain_part];
        }

        return $setting_tree;
    }

    // 寫用戶設定（chain 任意層數 → 沿路建樹，落 <data>/config/settings.json）
    public function set_setting($setting_chain, $value)
    {
        $setting_chain_parts = explode(".", $setting_chain);

        // 判定：空 chain → 拒絕
        if ($setting_chain === "") {
            return false;
        }

        $setting_group = array();
        $setting_node  = &$setting_group;

        foreach ($setting_chain_parts as $setting_chain_part) {
            $setting_node[$setting_chain_part] = array();
            $setting_node = &$setting_node[$setting_chain_part];
        }

        $setting_node = $value;

        return $this->merge_settings($setting_group);
    }

    // 合併用戶設定（大陣列一次過——新 key 加入、重複 key 覆蓋；只動記憶體）
    public function merge_settings($settings)
    {
        // 判定：唔係陣列 → 拒絕
        if (is_array($settings) === false) {
            return false;
        }

        $this->latest_settings = array_replace_recursive($this->latest_settings, $settings);

        return true;
    }

    // 讀取（點分隔巢狀；無值回 default）
    public function get_config($config_chain, $default = null)
    {
        $config_chain_parts = explode(".", $config_chain);
        $config_tree = $this->latest_configs;

        foreach ($config_chain_parts as $config_chain_part) {
            // key 不存在 → 回 default
            if (!isset($config_tree[$config_chain_part])) {
                return $default;
            }

            $config_tree = $config_tree[$config_chain_part];
        }

        return $config_tree;
    }
    
    // 寫入（點分隔巢狀；建立缺失層）
    public function set_config($config_chain, $value)
    {
        $config_chain_parts = explode(".", $config_chain);

        // 判定：空 chain → 拒絕
        if ($config_chain === "") {
            return false;
        }

        $config_group = array();
        $config_node  = &$config_group;

        foreach ($config_chain_parts as $config_chain_part) {
            $config_node[$config_chain_part] = array();
            $config_node = &$config_node[$config_chain_part];
        }

        $config_node = $value;

        return $this->merge_configs($config_group);
    }
}

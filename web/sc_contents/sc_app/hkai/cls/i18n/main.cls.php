<?php

namespace sc\app\hkai\cls\i18n;

// == main_Class：hkai app 層 i18n（singleton——經 bridging hkai__i18n() 取得） ==
// == 字典檔：cls/i18n/lang/{lang_code}.json（flat dot-key——PHP 與 JS 共用同一份檔） ==
// == 語系碼：讀設定（sc_config/hkai/settings.json）；未設定 → 預設 zh-HK ==

class main_Class
{
    private static $instance = null;
    
    private $lang_data = null;
    private $loaded_lang_code = null;
    
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
    
    // 目前語系碼（sc_config/hkai/settings.json；未設定 → 預設 zh-HK）
    public function get_lang_code()
    {
        // 設定（sc_config/hkai/settings.json——語言 SSOT＝general 組嘅 language）
        $effective_settings = hkai__core()->read_effective_settings();
        $general_settings   = $effective_settings["general"] ?? [];
        $lang_code          = $general_settings["language"] ?? "zh-HK";

        return $lang_code;
    }
    
    // 字典資料（快取——語系碼不變不重讀）
    public function get_lang_data()
    {
        $lang_code = $this->get_lang_code();
        
        // 判定：語系碼與已載入不同 → 重新載入
        if ($this->loaded_lang_code !== $lang_code) {
            $this->lang_data        = $this->load_lang_file($lang_code);
            $this->loaded_lang_code = $lang_code;
        }
        
        return $this->lang_data;
    }
    
    // 查字（未命中 → 回 key 本身）
    public function get($key)
    {
        $lang_data = $this->get_lang_data();
        
        // 判定：字典有此 key → 回譯文
        if (array_key_exists($key, $lang_data)) {
            return $lang_data[$key];
        }
        
        return $key;
    }
    
    // 載入語系檔（不存在／解析失敗 → 空字典）
    private function load_lang_file($lang_code)
    {
        $lang_file = __DIR__ . "/lang/" . $lang_code . ".json";
        
        // 判定：語系檔不存在 → 空字典
        if (!is_file($lang_file)) {
            return [];
        }
        
        $json_content = file_get_contents($lang_file);
        $lang_data    = json_decode($json_content, true);
        
        // 判定：解析失敗 → 空字典
        if (!is_array($lang_data)) {
            return [];
        }
        
        return $lang_data;
    }
}

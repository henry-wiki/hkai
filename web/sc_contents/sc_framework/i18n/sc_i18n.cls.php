<?php
namespace sc\framework\i18n;

class sc_i18n_Class
{
    private static $instance = null;
    
    private $lang_data = array();
    private $loaded_lang_code = null;
    
    public static function getInstance()
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
    }
    
    // 當前語言碼（與 sc_locale 同一 session key，零依賴；無則 fallback en）
    public function get_lang_code()
    {
        $lang_code = (isset($_SESSION["_lang"]) ? $_SESSION["_lang"] : null);
        
        // session 無語言碼 → 預設 en
        if (!@$lang_code) {
            $lang_code = "en";
        }
        
        return $lang_code;
    }
    
    // 字典資料（載入 lang/{code}.php；缺檔 fallback en；僅首次載入）
    public function get_lang_data()
    {
        $lang_code = $this->get_lang_code();
        
        // 語言碼與已載入不同 → 重新載入
        if ($this->loaded_lang_code !== $lang_code) {
            $this->lang_data = $this->load_lang_file($lang_code);
            $this->loaded_lang_code = $lang_code;
        }
        
        return $this->lang_data;
    }
    
    // 翻譯：group 用法為 key 前綴（"member.xxx"）；未命中回傳 key 本身
    public function translate($key, $group = null)
    {
        $lookup_key = $key;
        
        // 有 group → 先查 group 前綴 key
        if (@$group) {
            $lookup_key = $group . "." . $key;
        }
        
        $lang_data = $this->get_lang_data();
        
        // group 前綴 key 命中 → 直接回傳
        if (array_key_exists($lookup_key, $lang_data)) {
            return $lang_data[$lookup_key];
        }
        
        // 無 group 或 group key 未命中 → 查原始 key
        if (array_key_exists($key, $lang_data)) {
            return $lang_data[$key];
        }
        
        return $key;
    }
    
    // 載入語言檔（指定語言檔不存在 → fallback en）
    private function load_lang_file($lang_code)
    {
        $lang_file = __DIR__ . "/../lang/" . $lang_code . ".php";
        
        // 指定語言檔不存在 → 改載 en
        if (!@file_exists($lang_file)) {
            $lang_file = __DIR__ . "/../lang/en.php";
        }
        
        $lang_data = @include($lang_file);
        
        // 檔案內容非 array → 空字典
        if (!@is_array($lang_data)) {
            $lang_data = array();
        }
        
        return $lang_data;
    }
}

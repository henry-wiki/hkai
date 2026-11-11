<?php

namespace sc\config;

// == sc_api_keys：用戶可設定 API key 儲存（data/config/api_keys.json）
// == 職責：讀／寫全部 *_API_KEY（單一檔案——PHP 與 Python 共用同一份）
// == 上游：provider 設定頁（儲存 token）、setup 設定頁、Python sc_env.load_api_keys()
// == 下游：<data_dir>/config/api_keys.json（原子覆寫）
// == 次序：JSON 有值 → 用 JSON；冇 → fallback .env（dev／未遷移期）

class sc_api_keys_Class
{
    private static $instance = null;

    private $key_values = array();
    private $loaded = false;

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
        $this->init_api_keys();
    }

    // 設定檔路徑（<data_dir>/config/api_keys.json）
    public function get_config_file()
    {
        return sc_data_path()->get_data_sub_dir("config/api_keys.json");
    }

    // 初始化 key 表（讀 config/api_keys.json——只讀一次；無檔／解析失敗＝空表）
    public function init_api_keys()
    {
        // 判定：已載入 → 跳過
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
        $config_file  = $this->get_config_file();

        // 判定：檔案唔存在 → 空表（之後靠 .env fallback）
        if (!is_file($config_file)) {
            return;
        }

        $config_text  = file_get_contents($config_file);
        $config_value = json_decode($config_text, true);

        // 判定：解析失敗 → 空表
        if (!is_array($config_value)) {
            return;
        }

        $this->key_values = $config_value;
    }

    // 讀單一 key（JSON 冇 → fallback .env）
    public function get_api_key($key, $default = null)
    {
        // 判定：JSON 有值（非空字串）→ 回 JSON
        if (isset($this->key_values[$key])) {
            return $this->key_values[$key];
        }

        $env_value = sc_env()->get_env($key, $default);

        return $env_value;
    }

    // 寫單一 key（空字串＝清除；原子覆寫）
    public function set_api_key($key, $value)
    {
        $this->init_api_keys();

        $this->key_values[$key] = $value;

        $this->write_file();
    }

    // 寫入 JSON（sc_write_json——美化＋建目錄＋原子 rename）
    private function write_file()
    {
        sc_write_json($this->get_config_file(), $this->key_values);
    }
}

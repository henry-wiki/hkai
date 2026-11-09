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
        $this->load();
    }

    // 設定檔路徑（<data_dir>/config/api_keys.json）
    public function get_config_file()
    {
        return sc_data_path()->get_data_sub_dir("config/api_keys.json");
    }

    // 載入全部 key（__construct 呼叫一次）
    public function load()
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
    public function get($key, $default = null)
    {
        // 判定：JSON 有值（非空字串）→ 回 JSON
        if (isset($this->key_values[$key])) {
            return $this->key_values[$key];
        }

        $env_value = sc_env()->get_env($key, $default);

        return $env_value;
    }

    // 讀全部（JSON 原形）
    public function get_all()
    {
        return $this->key_values;
    }

    // 寫單一 key（空字串＝清除；原子覆寫）
    public function set($key, $value)
    {
        $this->load();

        $this->key_values[$key] = $value;

        $this->write_file();
    }

    // 寫入 JSON（先建目錄→暫存檔→rename）
    private function write_file()
    {
        $config_file     = $this->get_config_file();
        $config_dir      = dirname($config_file);
        $config_text     = json_encode($this->key_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $temporary_file  = $config_file . ".tmp";

        // 判定：目錄唔存在 → 建立
        if (!is_dir($config_dir)) {
            mkdir($config_dir, 0777, true);
        }

        file_put_contents($temporary_file, $config_text . "\n", LOCK_EX);

        rename($temporary_file, $config_file);
    }
}

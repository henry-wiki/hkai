<?php

namespace sc\app\hkai\cls;

// == hkai_Class：hkai app 核心（singleton——由 bridging hkai() 取得） ==
// == initial()：app 啟動入口（manifest 設定＋sqlite 連線） ==
// == 職責：manifest 設定讀取（get_hkai_configs）＋域 accessor（runtime／page／activity／agent／process） ==

class hkai_Class
{
    private static $instance = null;
    
    
    private $hkai_configs = [];
    
    // 固定路徑（initial() 一次推導——storage_dir ＋ web/sc_config/hkai/hkai_config.jsonon SSOT）
    
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

    /**
     * runtime 域（runtime.json 讀寫＋檢查）
     *
     * @return \sc\app\hkai\cls\runtime\runtime_Class
     */
    public function runtime()
    {
        return runtime\runtime_Class::getInstance();
    }

    /**
     * i18n 域（語言字串）
     *
     * @return \sc\app\hkai\cls\i18n\i18n_Class
     */
    public function i18n()
    {
        return i18n\i18n_Class::getInstance();
    }

    /**
     * settings 域（用戶偏好——出廠＋web/sc_contents/sc_app/hkai/cls/settings/settings.phpon 覆蓋）
     *
     * @return \sc\app\hkai\cls\settings\settings_Class
     */
    public function settings()
    {
        return settings\settings_Class::getInstance();
    }

    /**
     * web_page 域（整頁組裝＋頁面記憶——子域經 ->builder()／->activity()）
     *
     * @return \sc\app\hkai\cls\web_page\web_page_Class
     */
    public function web_page()
    {
        return web_page\web_page_Class::getInstance();
    }

    /**
     * agent 域（agent 選擇＋對映表）
     *
     * @return \sc\app\hkai\cls\agent\agent_Class
     */
    public function agent()
    {
        return agent\agent_Class::getInstance();
    }

    /**
     * process 域（Python bridge 生命週期＋本機程序——子域經 ->bridge() 等）
     *
     * @return \sc\app\hkai\cls\process\process_Class
     */
    public function process()
    {
        return process\process_Class::getInstance();
    }

    
    // 初始化（__init__.php 載入語境時呼叫一次——manifest 設定＋sqlite 連線）
    public function initial()
    {
        // 讀取靜態 SSOT（hkai_config.json——路徑由 sc_config 提供）
        $this->load_hkai_config();

        // 注入 sqlite 路徑（值＝單一來源 sc_config storage.database_file）
        $databaseFilePath = sc_get_config("storage.database_file");
        sc_set_config("database.connections.sqlite.database", $databaseFilePath);

        // 建立並快取連線（其他 plugin 以 sc_db()->connection("sqlite") 取回同一實例）
        sc_db()->connection("sqlite");
    }

    // 讀取 hkai_config.json（SSOT 靜態設定——失敗寫 runtime log、不中斷流程）
    private function load_hkai_config()
    {
        $hkai_config_file = sc_get_config("hkai.config_file");

        // 判定：檔案不存在 → 寫 log、中止
        if (!is_file($hkai_config_file)) {
            hkai()->runtime()->logger()->error("hkai_config not found: " . $hkai_config_file);
            return;
        }

        // 判定：JSON 解析失敗 → 寫 log、中止
        $hkai_config_data = json_decode(@file_get_contents($hkai_config_file), true);
        if (!is_array($hkai_config_data)) {
            hkai()->runtime()->logger()->error("hkai_config unreadable or invalid JSON: " . $hkai_config_file);
            return;
        }

        $this->hkai_configs = $hkai_config_data;

    }

    // 取得 hkai_configs（config_key＝頂層 key；null → 全部；key 存在 → 該項；key 不符 → 寫 log、回 null）
    public function get_hkai_configs($config_key = null)
    {
        // 判定：無參數 → 回傳全部
        if ($config_key === null) {
            return $this->hkai_configs;
        }

        // 判定：key 存在 → 回傳該項
        if (array_key_exists($config_key, $this->hkai_configs)) {
            return $this->hkai_configs[$config_key];
        }

        // key 不符 → 寫 log、回 null
        hkai()->runtime()->logger()->error("hkai_configs key not found: " . $config_key);
        return null;
    }

    
}

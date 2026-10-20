<?php
namespace sc\config;

// == sc_config：config 檔集合載入器與讀寫（對應 Laravel Illuminate\Config\Repository） ==
// == 位置：web/sc_config/cls/（域 cls 根——與 config 檔同區；2026-10-12 自框架 foundation/config 搬入） ==
// == load()：__construct 時掃描值檔目錄（web/sc_config/assets/）＋最新配置覆蓋（sc_set_configs() 交入） ==
// == get()/set()：點分隔巢狀 key（sc_get_config('app.version')） ==

class sc_config_Class
{
    private static $instance = null;
    private static $latest_config = array();
    
    private $items = array();
    private $loaded = false;
    
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
        $this->load();
    }
    
    // 載入全部 config 檔（__construct 呼叫一次——對應 Laravel LoadConfiguration；config 檔目錄在應用層）
    public function load()
    {
        // 已載入 → 跳過（載入一次即可；掃描中重入＝亦即刻返）
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        
        // 最新配置先墊（掃描期間取配置＝有值——如 session.php 讀 runtime.tmp）
        $this->items = self::$latest_config;
        
        // config 值檔目錄（web/sc_config/assets/——上層推導；唔靠 SC_* 常數：常數由本倉派生，初始載入時尚未定義）
        $configDir = dirname(__DIR__) . "/assets";
        $configFiles = glob($configDir . "/*.php");
        
        foreach ($configFiles as $configFile) {
            $fileName = basename($configFile, ".php");
            
            // require_once——防重複載入
            $fileItems = require_once $configFile;
            
            // 非 array（define 檔／已載入回 true）→ 跳過；array 檔以檔名為 key 深層合併
            if (!is_array($fileItems)) {
                continue;
            }
            
            // 值檔深層併入（掃描期間＝最新配置與值檔共存）
            $existingItems = $this->items[$fileName] ?? array();
            $this->items[$fileName] = array_replace_recursive($existingItems, $fileItems);
        }
        
        // 最新配置逐個覆蓋值檔（重複 key＝以交入者為準——它是最新）
        $this->items = array_replace_recursive($this->items, self::$latest_config);
    }
    
    // 設定最新配置（load.php 交入嘅大陣列——重複者逐個覆蓋值檔）
    public static function set_latest_config($config)
    {
        self::$latest_config = $config;
    }
    
    // 讀取（點分隔巢狀；無值回 default）
    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $currentItems = $this->items;
        
        foreach ($keys as $keyPart) {
            // key 不存在 → 回 default
            if (!isset($currentItems[$keyPart])) {
                return $default;
            }
            $currentItems = $currentItems[$keyPart];
        }
        
        return $currentItems;
    }
    
    // 讀取全部（大陣列原形——sc_get_configs() 用）
    public function get_all()
    {
        return $this->items;
    }
    
    // 寫入（點分隔巢狀；建立缺失層）
    public function set($key, $value)
    {
        $keys = explode('.', $key);
        $currentItems = &$this->items;
        
        foreach ($keys as $keyPart) {
            // 無此層或非陣列 → 建空陣列再深入
            if (!isset($currentItems[$keyPart]) || !is_array($currentItems[$keyPart])) {
                $currentItems[$keyPart] = array();
            }
            $currentItems = &$currentItems[$keyPart];
        }
        
        $currentItems = $value;
    }
}

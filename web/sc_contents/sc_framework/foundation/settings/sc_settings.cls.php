<?php
namespace sc\framework\foundation\settings;

// == settings：動態設定容器（CONFIG 存預設，SETTING 運行時讀寫覆寫） ==
// == 遷自 config/__instance__.php（ConfigClass + PublicConfigClass 雙 class 合併） ==

class sc_settings_Class
{
    // 單例實例
    private static $instance = null;
    
    // 私有設定層（後台用）
    private $config = [];
    
    // 公開設定層（前台 JS 可讀）
    private $public_config = [];
    
    // 私有建構子，防止外部實例化
    private function __construct()
    {
    }
    
    // 取得單例
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    // 設定 key（點分隔巢狀；存私有層）
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $part) {
            if (!isset($config[$part]) || !is_array($config[$part])) {
                $config[$part] = [];
            }
            $config = &$config[$part];
        }
        
        $config = $value;
    }
    
    // 讀取 key（點分隔巢狀；私有層，無值回 default）
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $config = $this->config;
        
        foreach ($keys as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            $config = $config[$part];
        }
        
        return $config;
    }
    
    // 批量設定（私有層）
    public function set_multiple(array $configs): void
    {
        foreach ($configs as $key => $value) {
            $this->set($key, $value);
        }
    }
    
    // 全部私有設定
    public function get_all(): array
    {
        return $this->config;
    }
    
    // 設定公開 key（前台可讀）
    public function set_public(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->public_config;
        
        foreach ($keys as $part) {
            if (!isset($config[$part]) || !is_array($config[$part])) {
                $config[$part] = [];
            }
            $config = &$config[$part];
        }
        
        $config = $value;
    }
    
    // 讀取公開 key（無值回 default）
    public function get_public(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $config = $this->public_config;
        
        foreach ($keys as $part) {
            if (!isset($config[$part])) {
                return $default;
            }
            $config = $config[$part];
        }
        
        return $config;
    }
    
    // 批量設定（公開層）
    public function set_public_multiple(array $configs): void
    {
        foreach ($configs as $key => $value) {
            $this->set_public($key, $value);
        }
    }
    
    // 全部公開設定
    public function get_all_public(): array
    {
        return $this->public_config;
    }
    
    // 防止克隆
    private function __clone() {}
    
    // 防止序列化
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}

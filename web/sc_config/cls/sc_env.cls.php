<?php
namespace sc\config;

// == sc_env：env 檔（.env）載入器與讀寫（sc_config 同款造法——平面鍵值） ==
// == 位置：web/sc_config/cls/（域 cls 根——與 config 同區） ==
// == load()：__construct 時讀 .env 檔（parse_ini_file RAW——與 setup／app 同一讀法） ==
// == get()：平面鍵讀取；set()：填值寫入（缺先加·已有唔覆蓋——寫 .env 檔） ==

class sc_env_Class
{
    private static $instance = null;
    
    private $env_values = array();
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
    }
    
    // 載入 .env 全部值（init.php 呼叫一次——固定 SC_ENV_FILE）
    public function load()
    {
        // 已載入 → 跳過（載入一次即可）
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        // env 檔唔存在 → 空（固定位置：SC_ENV_FILE——init.php define）
        if (!is_file(SC_ENV_FILE)) {
            return;
        }
        
        $env_values = parse_ini_file(SC_ENV_FILE, false, INI_SCANNER_RAW);
        
        // 解析失敗（非陣列）→ 空
        if (!is_array($env_values)) {
            return;
        }
        
        $this->env_values = $env_values;
    }
    
    // 讀取（平面鍵；無值回 default）
    // 初始化執行環境（入口最早呼叫——路徑洗淨；.env 延後至首次取值才載入）
    public function init_running_environment()
    {
        $this->normalize_server_paths();
    }

    // 路徑洗淨：就地改寫 $_SERVER（FastCGI 裝置前綴＋反斜線——之後派生路徑即準確）
    public function normalize_server_paths()
    {
        $server_path_keys = array("SCRIPT_FILENAME", "PATH_TRANSLATED", "DOCUMENT_ROOT", "ORIG_SCRIPT_FILENAME");

        // 逐個 key 就地洗淨（轉換交純函數 sc_normalize_path）
        foreach ($server_path_keys as $server_path_key) {
            // 判定：冇呢個 key → 跳過
            if (isset($_SERVER[$server_path_key]) === false) {
                continue;
            }

            $server_path_value = sc_normalize_path($_SERVER[$server_path_key]);

            $_SERVER[$server_path_key] = $server_path_value;
        }
    }

    public function get_env($key, $default = null)
    {
        // key 不存在 → 回 default
        if (!isset($this->env_values[$key])) {
            return $default;
        }
        
        return $this->env_values[$key];
    }
    
    // 設定 env 值（只寫記憶體——本 request 有效；落檔交 save_env）
    public function set_env($key, $value)
    {
        $this->env_values[$key] = $value;
    }
}

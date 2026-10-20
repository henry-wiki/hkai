<?php

namespace sc\app\hkai\cls\sc_runtime;

// == sc_runtime_Class：hkai app 層 runtime 中樞（singleton——經 bridging hkai__sc_runtime() 取得） ==
// == 職責：runtime 檢查（check）＋ runtime config 變更判定（is_runtime_config_changed） ==
// == 寫入交由 sc_runtime_io_Class（bridging hkai__sc_runtime_io()） ==

class sc_runtime_Class
{
    private static $instance = null;
    
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
    
    // runtime 設定檢查（必要設定缺失／不可用 → 寫 runtime log；不中斷流程）
    public function check()
    {
        $python_executable = hkai__core()->get_python_executable();
        $runtime_configs   = hkai__core()->get_runtime_configs();
        $storage_dir       = $runtime_configs["storage_dir"];
        
        // ① python_executable 未設定
        if ($python_executable === "") {
            hkai__sc_runtime_io()->error("runtime check: python_executable not set");
        }
        
        // ① python_executable 已設定 → 檢查檔案存在
        if ($python_executable !== "") {
            if (!file_exists($python_executable)) {
                hkai__sc_runtime_io()->error("runtime check: python_executable not found: " . $python_executable);
            }
        }
        
        // ② storage 目錄不可寫
        if (!is_writable($storage_dir)) {
            hkai__sc_runtime_io()->error("runtime check: storage dir not writable: " . $storage_dir);
        }
    }
    
    // runtime.json 絕對位置（委託 hkai_core——入口定義、python 唯一動態設定來源）
    public function get_runtime_config_file()
    {
        return hkai__core()->get_runtime_config_file();
    }

    // runtime.json 是否與最新 runtime configs 不同（檔案不存在 / checksum 不符 → true）
    public function is_runtime_config_changed()
    {
        $daemon_config   = hkai__core()->get_daemon_config();
        $runtime_configs = hkai__core()->get_runtime_configs();
        
        $runtime_configs_json     = json_encode($runtime_configs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $runtime_configs_checksum = md5($runtime_configs_json);
        
        $runtime_config_path = $daemon_config["runtime_config_file"];
        
        // 檔案不存在 → 視為已變更
        if (!@file_exists($runtime_config_path)) {
            return true;
        }
        
        // checksum 不符 → 已變更
        if (md5_file($runtime_config_path) !== $runtime_configs_checksum) {
            return true;
        }
        
        return false;
    }
}

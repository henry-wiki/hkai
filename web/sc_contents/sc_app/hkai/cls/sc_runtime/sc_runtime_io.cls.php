<?php

namespace sc\app\hkai\cls\sc_runtime;

// == sc_runtime_io_Class：hkai app 層 runtime log 寫入（singleton——經 bridging hkai__sc_runtime_io() 取得） ==
// == 職責：write($level, $message)——每日一檔 {log_dir}/hkai.{Ymd}.log（格式同框架 sc_router 的 route_logs） ==
// == log_dir 由 __init__.php 入口注入（initial——class 只收值） ==

class sc_runtime_io_Class
{
    private static $instance = null;
    
    // runtime log 目錄（__init__.php 注入——sc_storage/hkai/logs）
    private $log_dir = null;
    
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
    
    // 注入 log 目錄（__init__.php 入口呼叫一次；key 同名）
    public function initial($runtime_configs)
    {
        $log_dir = $runtime_configs["log_dir"];
        
        $this->log_dir = $log_dir;
    }
    
    // 寫入一行（每日一檔——log_dir 不存在則建立）
    public function write($level, $message)
    {
        // log 目錄不存在 → 建立（空目錄不進版控）
        if (!is_dir($this->log_dir)) {
            sc_mkdir($this->log_dir);
        }
        
        $log_file = $this->log_dir . "/hkai." . date("Ymd") . ".log";
        $log_line = "[" . date("Y-m-d H:i:s") . "] [" . $level . "] " . $message . "\r\n";
        
        @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
    
    // 語法糖：INFO
    public function info($message)
    {
        $this->write("INFO", $message);
    }
    
    // 語法糖：WARN
    public function warn($message)
    {
        $this->write("WARN", $message);
    }
    
    // 語法糖：ERROR
    public function error($message)
    {
        $this->write("ERROR", $message);
    }
}

<?php

namespace sc\app\hkai\cls\runtime\logger;

// == logger_Class：hkai app 層 log 寫入（singleton——經 bridging hkai()->runtime()->logger() 取得） ==
// == 職責：write($level, $message)——每日一檔 {log_dir}/hkai.{Ymd}.log（格式同框架 sc_router route_logs）
// == 上游：全部 PHP 模組（info／warn／error 語法糖）
// == 下游：sc_config("hkai.log_dir")（路徑單一來源）、sc_mkdir（目錄建立）

class logger_Class
{
    private static $instance = null;

    // log 目錄（sc_config 提供——load.php hkai.log_dir）
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
    {
        $this->log_dir = sc_get_config("hkai.log_dir");
    }

    // 寫入一行（每日一檔——目錄不存在則建立）
    public function write($level, $message)
    {
        // 判定：log 目錄不存在 → 建立（空目錄不進版控）
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

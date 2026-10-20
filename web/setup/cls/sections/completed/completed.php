<?php

namespace sc\setup\sections;

// 載入本格內部短名（completed.func.php——setup() 向上接駁；include once）
require_once __DIR__ . "/completed.func.php";

// 載入本格 view 側 trait（五個 build 方法——include once）
require_once __DIR__ . "/completed.view.php";

// == HKAI setup sections/completed completed.php：completed 格本體（class＋view trait——側檔＝completed.view.php；呼叫形 setup()->sections()->completed()）
// == 職責：本格整件（build_section＝head＋body＋footer——每格獨立決定）；兩態——已安裝（感謝頁：感謝語＋關閉掣）／未安裝（統計摘要）
class completed_Class
{
    use completed_view;

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
    {
    }
    

}

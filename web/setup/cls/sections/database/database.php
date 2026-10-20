<?php

namespace sc\setup\sections;

// 短名域（database 域單元——檢查表直取）
use sc\setup\sections\database;

// 載入 database 格內部短名（database.func.php——setup()／域門）
require_once __DIR__ . "/database.func.php";

// 載入本格 view 側 trait（三個 view 方法——include once）
require_once __DIR__ . "/database.view.php";

// == HKAI setup sections database：資料庫格單元（格本體——view 側＝database.view.php／檢查項對映＝sections 域 get_section_unit_instance；呼叫方式 setup()->sections()->database()）
// == 職責：本格整件（build_section＝head＋body＋footer——每格獨立決定）；檢查表內置（逐項一檢查單元——單元自報結果）

class database_Class
{
    use database_view;

    private static $instance = null;
    
    // 檢查表（what to check——key＝<單元名>（如 db_ready）·值＝Closure（直呼檢查單元——F12 跳得到）；由 __construct 砌）
    private $check_items = [];
    
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
        // 砌檢查表（一項一 Closure——直呼檢查單元·F12 跳得到；單元回 status（pass／fail／warn）＋label／detail／hint）
        $this->check_items = [];
        $this->check_items["db_ready"] = function () {
            return database\db_ready()->setup_check();
        };
        
    }

    // 跑單一檢查項（按 code 叫返對應檢查單元——跑咗回檢查結果；未知＝null）
    public function run_check_item($check_item_code)
    {
        // 判定：表內有此項 → 跑佢（closure＝唯一真實檢查入口）
        if (isset($this->check_items[$check_item_code])) {
            return $this->check_items[$check_item_code]();
        }

        return null;
    }

    public function get_state_value($check_item_code='',$value='')
    {
        if($check_item_code === "")
        {
            // == invalid check item code
            return ;
        }

        $state_value = [];
        $state_value["value"] = "";
        $state_value["update_at"] = date("Y-m-d H:i:s");

        if($check_item_code === "db_ready")
        {
            $state_value["value"] = $value;
        }

        return $state_value;
    }
}

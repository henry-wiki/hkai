<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\normalize;

// == sc_ai_chats message\normalize 子域：bridge 檔——只放 class（.cls.php＝class 專用；.func.php＝function 專用） ==
// == 載入：autoloader（一般映射先行、本目錄 bridge 檔後備——_.bridge.cls.php 系統指定名） ==
// == 唯一入口：sc_ai_chats()->message()->normalize() ==

class normalize_Class
{
    use response_brief_rows;
    use get_response_type_from_poll_file;
    use get_response_index_from_poll_file;

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

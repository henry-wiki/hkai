<?php

namespace sc\app\hkai\plugins\sc_ai_chats\poll\file;

// == sc_ai_chats poll\file：bridge 檔——只放 class（.cls.php＝class 專用；.func.php＝function 專用） ==
// == 載入：autoloader（一般映射先行、本目錄 bridge 檔後備——_.bridge.cls.php 系統指定名） ==
// == 唯一入口：sc_ai_chats()->poll()->file() ==

class file_Class
{
    use get_poll_dirs;
    use get_msg_conf_data;
    use get_msg_poll_list;
    use get_response_sections;
    use poll_jobs;
    use get_poll_status;

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

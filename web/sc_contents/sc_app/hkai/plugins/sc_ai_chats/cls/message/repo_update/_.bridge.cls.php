<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_update;

// == sc_ai_chats message\repo_update：bridge 檔——只放 class（.cls.php＝class 專用；.func.php＝function 專用） ==
// == 載入：autoloader（一般映射先行、本目錄 bridge 檔後備——_.bridge.cls.php 系統指定名） ==
// == 唯一入口：sc_ai_chats()->message()->repo_update() ==

class repo_update_Class
{
    use close_db_message_response_rows;
    use delete_db_message;
    use insert_db_message;
    use insert_db_message_failed;
    use update_db_message_response_timing;
    use update_db_message_status;
    use update_db_message_status_repair_meta;
    use update_db_message_timing;
    use upsert_db_message_response;

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

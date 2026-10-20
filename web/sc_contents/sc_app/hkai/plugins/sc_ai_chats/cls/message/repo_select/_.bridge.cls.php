<?php

namespace sc\app\hkai\plugins\sc_ai_chats\message\repo_select;

// == sc_ai_chats message\repo_select：bridge 檔——只放 class（.cls.php＝class 專用；.func.php＝function 專用） ==
// == 載入：autoloader（一般映射先行、本目錄 bridge 檔後備——_.bridge.cls.php 系統指定名） ==
// == 唯一入口：sc_ai_chats()->message()->repo_select() ==

class repo_select_Class
{
    use get_message_rows;
    use get_message_row;
    use get_message_response_brief_rows;
    use get_message_response_rows_by_message_id;
    use join_response_answer_contents;

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

<?php

namespace sc\app\hkai\plugins\sc_ai_settings\general;

// == sc_ai_settings general：general 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->general()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：General 分頁內容（業界慣例：語言／外觀等偏好集中此頁——本頁為 settings 第一頁） ==

class general_Class
{
    use view\handle_fetch_general;
    use action\handle_settings_save_general;

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
}

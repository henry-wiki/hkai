<?php

namespace sc\app\hkai\plugins\sc_ai_settings\about;

// == sc_ai_settings about：about 域本體（bridge 檔——singleton；呼叫形 sc_ai_settings()->about()） ==
// == 動作（trait——一檔一動作） ==
// == 職責：關於 HKAI 分頁內容（值＝sc_config/hkai/hkai_config.json about 節——唯一來源） ==

class about_Class
{
    use view\handle_fetch_about;

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

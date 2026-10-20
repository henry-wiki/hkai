<?php

namespace sc\app\hkai\plugins\sc_ai_projects\modal;

// == sc_ai_projects modal：modal 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->modal()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat class 合成） ==

class modal_Class
{
    use tabs\get_modal_tab;
    use tabs\get_modal_tabs;
    use view\build_modal_head;
    use view\build_modal_tabs;
    use view\fetch_project_form;

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

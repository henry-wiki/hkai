<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_delete_project;

// == sc_ai_projects tab_delete_project：tab_delete_project 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->tab_delete_project()） ==
// == 動作（trait——一檔一動作） ==

class tab_delete_project_Class
{
    use view\build_tab_delete_project_body;
    use view\build_tab_delete_project_head;

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

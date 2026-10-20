<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_environment;

// == sc_ai_projects tab_environment：tab_environment 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->tab_environment()） ==
// == 動作（trait——一檔一動作） ==

class tab_environment_Class
{
    use view\build_tab_environment_body;
    use view\build_tab_environment_foot;
    use view\build_tab_environment_foot_note;
    use view\build_tab_environment_head;
    use action\write_project_environment_file;
    use action\render_project_environment_markdown;
    use action\update_tab_environment;

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

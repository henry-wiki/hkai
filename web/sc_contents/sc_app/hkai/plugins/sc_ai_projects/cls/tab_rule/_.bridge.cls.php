<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_rule;

// == sc_ai_projects tab_rule：tab_rule 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->tab_rule()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat class 合成） ==

class tab_rule_Class
{
    use view\build_notice_pane;
    use view\build_tab_rule_body;
    use view\build_tab_rule_foot;
    use view\build_tab_rule_foot_note;
    use view\build_tab_rule_head;
    use action\save_tab_rule;
    use action\reset_tab_rule;

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

<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_general_form;

// == sc_ai_projects tab_general_form：tab_general_form 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->tab_general_form()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat class 合成） ==

class tab_general_form_Class
{
    use action\project_add;
    use action\project_archive;
    use action\project_delete;
    use action\project_edit;
    use action\read_input_data;
    use action\verify_project_data;
    use validate\validate_project_input;
    use view\build_field_row;
    use view\build_form_row;
    use view\build_general_form_tab_foot_add;
    use view\build_general_form_tab_foot_edit;
    use view\build_remove_row;
    use view\build_section_heading;
    use view\build_tab_general_form_body;
    use view\build_textarea_row;

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

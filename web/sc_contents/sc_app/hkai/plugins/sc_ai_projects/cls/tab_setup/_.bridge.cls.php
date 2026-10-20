<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup;

// == sc_ai_projects tab_setup：tab_setup 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->tab_setup()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat class 合成） ==

class tab_setup_Class
{
    use action\check_git_installed;
    use action\check_git_repo;
    use action\check_has_commit;
    use action\check_has_description;
    use action\check_has_gitignore;
    use action\check_has_remote;
    use action\check_path_exists;
    use action\has_ref_file;
    use action\run_checklist;
    use view\build_notice_pane;
    use view\build_tab_setup_body;
    use view\build_tab_setup_foot;
    use view\build_tab_setup_head;
    use view\render_check_item;
    use view\render_setup_panel;

    private static $git_installed_cache = null;

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

<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git;

// == sc_ai_projects tab_git：tab_git 域本體（bridge 檔——singleton；呼叫形 sc_ai_projects()->tab_git()） ==
// == 動作（trait——一檔一動作；2026-09-27 自舊 flat class 合成） ==

class tab_git_Class
{
    use action\git_commit;
    use action\git_init;
    use action\git_stage_all;
    use action\git_status;
    use action\run_python_git_commit;
    use action\run_python_git_init;
    use action\run_python_git_stage_all;
    use view\build_notice_pane;
    use view\build_tab_git_body;
    use view\build_tab_git_foot;
    use view\build_tab_git_head;
    use view\get_git_branch_text;
    use view\get_git_commit;
    use view\get_git_headline;
    use view\get_git_last_commit_text;
    use view\render_git_check_item;
    use view\render_git_status;

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

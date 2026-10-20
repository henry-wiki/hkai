<?php

namespace sc\app\hkai\plugins\sc_ai_projects;

// == sc_ai_projects_Class：插件頂層本體（singleton——bridging sc_ai_projects()） ==
// == 職責：域入口（accessor）——例：sc_ai_projects()->project()->get_project_row($project_slug) ==
// == 2026-09-27 域收攏（舊 15 支 sc_ai_projects__<x>() bridging 收成頂層單一入口） ==

class sc_ai_projects_Class
{

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

    /**
     * 專案域（project_Class——init／repo_select／repo_update／resolver／slug／view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\project\project_Class
     */
    public function project()
    {
        return project\project_Class::getInstance();
    }

    /**
     * modal 域（modal_Class——tabs／view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\modal\modal_Class
     */
    public function modal()
    {
        return modal\modal_Class::getInstance();
    }

    /**
     * 表單 tab 域（tab_general_form_Class——action／validate／view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\tab_general_form\tab_general_form_Class
     */
    public function tab_general_form()
    {
        return tab_general_form\tab_general_form_Class::getInstance();
    }

    /**
     * git tab 域（tab_git_Class——action／view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\tab_git\tab_git_Class
     */
    public function tab_git()
    {
        return tab_git\tab_git_Class::getInstance();
    }

    /**
     * 規則 tab 域（tab_rule_Class——view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\tab_rule\tab_rule_Class
     */
    public function tab_rule()
    {
        return tab_rule\tab_rule_Class::getInstance();
    }

    /**
     * 環境 tab 域（tab_environment_Class——view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\tab_environment\tab_environment_Class
     */
    public function tab_environment()
    {
        return tab_environment\tab_environment_Class::getInstance();
    }

    /**
     * 診斷 tab 域（tab_diagnostics_Class——view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\tab_diagnostics_Class
     */
    public function tab_diagnostics()
    {
        return tab_diagnostics\tab_diagnostics_Class::getInstance();
    }

    /**
     * 刪除 tab 域（tab_delete_project_Class——view）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\tab_delete_project\tab_delete_project_Class
     */
    public function tab_delete_project()
    {
        return tab_delete_project\tab_delete_project_Class::getInstance();
    }

    /**
     * 健康報告域（health_Class——1 動作）
     *
     * @return \sc\app\hkai\plugins\sc_ai_projects\health\health_Class
     */
    public function health()
    {
        return health\health_Class::getInstance();
    }

}

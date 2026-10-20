<?php

namespace sc\app\hkai\plugins\sc_ai_projects\modal;

// == sc_ai_projects modal 域 internal 短名（_.internal.func.php·子域版）：同 namespace 直接叫（view()——免 use function） ==
// == 委派全局 bridging（解析源單一——class 引用仍只喺插件 cls/_.func.php） ==
// == 載入：檔尾 .func.php——框架開機遞迴掃描自動載入（零註冊）；短名住子域 namespace＝同其它 plugin 隔離、零撞名 ==
// == 取名規則：本域單元用裸名（tabs／view）；跨域同名者帶域名（tab_general_form_view 等）；全域唯一者裸名（repo_select） ==

/**
 * repo_select 單例（跨域短名——例：repo_select()->get_project_row($project_slug)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\repo_select_Class
 */
function repo_select()
{
    return sc_ai_projects()->project();
}

/**
 * tabs 單例（本域短名——例：tabs()->get_modal_tab($tab_id)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\modal\tabs_Class
 */
function tabs()
{
    return sc_ai_projects()->modal();
}

/**
 * view 單例（本域短名——例：view()->build_modal_head($is_edit)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\modal\view_Class
 */
function view()
{
    return sc_ai_projects()->modal();
}

/**
 * tab_general_form_view 單例（跨域短名——例：tab_general_form_view()->build_tab_general_form_body($project_row)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\tab_general_form\view_Class
 */
function tab_general_form_view()
{
    return sc_ai_projects()->tab_general_form();
}

/**
 * tab_git_view 單例（跨域短名——例：tab_git_view()->build_tab_git_body($project_row)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\tab_git\view_Class
 */
function tab_git_view()
{
    return sc_ai_projects()->tab_git();
}

/**
 * tab_rule_view 單例（跨域短名——例：tab_rule_view()->build_tab_rule_body($project_row)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\tab_rule\view_Class
 */
function tab_rule_view()
{
    return sc_ai_projects()->tab_rule();
}

/**
 * tab_environment_view 單例（跨域短名——例：tab_environment_view()->build_tab_environment_body($project_row)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\tab_environment\view_Class
 */
function tab_environment_view()
{
    return sc_ai_projects()->tab_environment();
}

/**
 * tab_diagnostics_view 單例（跨域短名——例：tab_diagnostics_view()->build_tab_diagnostics_body($project_row, $diagnostics_foot_html)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\tab_diagnostics\tab_diagnostics_Class
 */
function tab_diagnostics_view()
{
    return sc_ai_projects()->tab_diagnostics();
}

/**
 * tab_delete_project_view 單例（跨域短名——例：tab_delete_project_view()->build_tab_delete_project_body($project_row)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\tab_delete_project\tab_delete_project_Class
 */
function tab_delete_project_view()
{
    return sc_ai_projects()->tab_delete_project();
}

<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_git;

// == sc_ai_projects git 域 internal 短名（_.internal.func.php·子域版）：同 namespace 直接叫（repo_select()——免 use function） ==
// == 委派全局 bridging（解析源單一——class 引用仍只喺插件 cls/_.func.php） ==
// == 載入：檔尾 .func.php——框架開機遞迴掃描自動載入（零註冊）；短名住子域 namespace＝同其它 plugin 隔離、零撞名 ==

/**
 * repo_select 單例（對內短名——例：repo_select()->get_project_row($project_slug)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\repo_select_Class
 */
function repo_select()
{
    return sc_ai_projects()->project();
}

/**
 * modal_view 單例（跨域短名——例：modal_view()->build_modal_head($is_edit)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\modal\view_Class
 */
function modal_view()
{
    return sc_ai_projects()->modal();
}

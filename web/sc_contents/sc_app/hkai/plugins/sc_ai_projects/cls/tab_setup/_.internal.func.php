<?php

namespace sc\app\hkai\plugins\sc_ai_projects\tab_setup;

// == sc_ai_projects tab_setup 域 internal 短名（_.internal.func.php·子域版）：同 namespace 直接叫（checks()——免 use function） ==
// == 委派全局 bridging（解析源單一——class 引用仍只喺插件 cls/_.func.php） ==
// == 載入：檔尾 .func.php——框架開機遞迴掃描自動載入（零註冊）；短名住子域 namespace＝同其它 plugin 隔離、零撞名 ==
// == 取名規則：本域單元用裸名（checks）；跨域同名者帶域名；全域唯一者裸名（同 tab_general_form／modal 兩檔） ==

/**
 * checks 單例（本域短名——例：checks()->run_checklist($project_path, $description)）
 *
 * @return \sc\app\hkai\plugins\sc_ai_projects\tab_setup\checks_Class
 */
function checks()
{
    return sc_ai_projects()->tab_setup();
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

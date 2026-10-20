<?php

namespace sc\app\hkai\plugins\sc_ai_projects;

// == sc_ai_projects internal bridging（_.internal.func.php）：對內短名取用——同 namespace 直接叫（repo_select()） ==
// == 對外唔用（外部照舊 sc_ai_projects()->project() 或 sc_ai_projects\repo_select()） ==
// == 載入：檔尾 .func.php——框架開機遞迴掃描自動載入（零註冊） ==
// == 註：薄別名——解析源單一（class 引用仍只喺 _.func.php——改名／換法只改一處） ==
// == 註：唔放全域——短名（init 等）同其它 plugin 撞＝function 唔可 redeclare＝Fatal；子域檔用 use function ==
// == 註：子域短名——子域自開 cls/<域>/_.internal.func.php（同款薄別名、namespace＝該子域；域內直接叫） ==

// init 單例（對內短名——例：init()->get_initial_data("project_rows_by_id")）
function init()
{
    return sc_ai_projects()->project();
}


// repo_select 單例（對內短名——例：repo_select()->get_project_rows()）
function repo_select()
{
    return sc_ai_projects()->project();
}

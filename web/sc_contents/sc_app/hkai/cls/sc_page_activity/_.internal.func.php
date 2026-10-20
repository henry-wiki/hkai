<?php

namespace sc\app\hkai\cls\sc_page_activity;

// == sc_page_activity internal bridging（_.internal.func.php）：對內短名取用——同 namespace 直接叫（repo_update()／repo_select()） ==
// == 對外唔用（外部照舊 hkai__sc_page_activity() 或 ...\repo_update_Class） ==
// == 載入：檔尾 .func.php——框架開機遞迴掃描自動載入（零註冊） ==
// == 註：薄別名——解析源單一（class 引用仍只喺 _.func.php——改名／換法只改一處） ==
// == 註：唔放全域——短名（repo_update 等）同其它域撞＝function 唔可 redeclare＝Fatal；靠 namespace 隔離 ==

// repo_update 單例（對內短名——例：repo_update()->insert_activity($activity_datapairs)）
function repo_update()
{
    return hkai__sc_page_activity_repo_update();
}

// repo_select 單例（對內短名——例：repo_select()->get_latest_activity_row()）
function repo_select()
{
    return hkai__sc_page_activity_repo_select();
}

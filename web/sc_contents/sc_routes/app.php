<?php
// == sc_routes/app.php：app 路由集中檔（對應 Laravel routes/app.php——app 組 middleware） ==
// == 由 bootstrapper load_routes 載入 ==
// == 實際 route 統一在 sc_app/__register__.php（load_register 載入——集中管理防 pattern 撞名） ==
// == 本檔僅負責載入 register（各 app 的 route 集中宣告處） ==
// == url_path 固定結構 path（精確比對、無正則；未命中 → 404） ==

// 載入 app 組 route 集中檔（sc_app/__register__.php——app route 統一宣告處）
require_once SC_DIR_APP . "/__register__.php";


// i comment , pls ignore the follow app
/* 
// == chats/main（sc_ai_chats subplugin：頁面 GET／提交 POST） ==
sc_router()->add_get_route("~^chats/main$~", SC_DIR_APP . "/aider/subplugins/sc_ai_chats/index.php", "app");
sc_router()->add_post_route("~^chats/main$~", SC_DIR_APP . "/aider/subplugins/sc_ai_chats/_request/handler.php", "app");

// == product/*（sc_ai_projects subplugin：各頁面 GET／POST） ==
sc_router()->add_get_route("~^product/main$~"                  , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/main$~"                 , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/finished_product$~"      , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/finished_product$~"     , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/bom$~"                   , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/bom$~"                  , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/die_mould$~"             , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/die_mould$~"            , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/purchased_parts$~"       , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/purchased_parts$~"      , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/selectable_for_oc$~"     , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/selectable_for_oc$~"    , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/selectable_for_bo$~"     , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/selectable_for_bo$~"    , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/selectable_for_sample$~" , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/selectable_for_sample$~", SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/selectable_die_mould_for_sample$~", SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/selectable_die_mould_for_sample$~", SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");
sc_router()->add_get_route("~^product/files$~"                 , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/index.php", "app");
sc_router()->add_post_route("~^product/files$~"                , SC_DIR_APP . "/aider/subplugins/sc_ai_projects/_request/handler.php", "app");

// == 專案/對話頁正式路由（Laravel Route::get('/{project}/{conversation}') 對應——放最後：更特定路由先命中） ==
// == 捕獲組：([^/]+)=projectId、([^/]*)=conversationId（app 組：session + CSRF） ==
sc_router()->add_get_route("~^([^/]+)/([^/]*)$~", SC_DIR_APP . "/aider/index.php", "app");
*/

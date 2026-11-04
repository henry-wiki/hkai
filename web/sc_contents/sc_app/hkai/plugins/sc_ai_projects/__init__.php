<?PHP

// 載入本 plugin bridging（plugin __init__ 先於 cls func 自動載入階段——顯式載入；include_once 確保不重複）
require_once __DIR__ . "/cls/_.func.php";

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, [
    // plugin settings
    
    "asset" => [
        __DIR__ . "/asset/sc_ai_projects.css",
         
    ],
]);


$plugin->setEnabled(true);

// 註冊保留 slug（不可作為專案 slug——路由／框架路徑；集中於 plugin 初始化設定，可再增）
// Home 專案列（slug＝""——不可更新／刪除；每個請求確保存在）
sc_ai_projects()->project()->ensure_home_project();

sc_ai_projects()->project()->add_reserved_slugs([
    "archive",
    sc_get_config("hkai.default_project_slug"),
    "hkai",
    "docs",
    "index",
    "manifest",
    "projects",
    "request",
    "sc_contents",
    "vendor",
]);

// 註冊 request action（取資料端點：清單／表單／Git 狀態）
$plugin->add_request_action("project_list_fetch"    ,__DIR__ . "/request/project/project_list_fetch.php");
$plugin->add_request_action("project_item_fetch"    ,__DIR__ . "/request/project/project_item_fetch.php");
$plugin->add_request_action("project_restore"       ,__DIR__ . "/request/project/project_restore.php");
$plugin->add_request_action("fetch_project_form"    ,__DIR__ . "/request/modal/fetch_project_form.php");
$plugin->add_request_action("fetch_section"         ,__DIR__ . "/request/tab_diagnostics/fetch_section.php");
$plugin->add_request_action("verify_project_data"   ,__DIR__ . "/request/tab_general_form/verify_project_data.php");
$plugin->add_request_action("git_commit"            ,__DIR__ . "/request/tab_git/git_commit.php");
$plugin->add_request_action("git_init"              ,__DIR__ . "/request/tab_git/git_init.php");
$plugin->add_request_action("git_stage_all"         ,__DIR__ . "/request/tab_git/git_stage_all.php");
$plugin->add_request_action("save_tab_rule"         ,__DIR__ . "/request/tab_rule/save_tab_rule.php");
$plugin->add_request_action("reset_tab_rule"        ,__DIR__ . "/request/tab_rule/reset_tab_rule.php");

$plugin->add_request_action("update_tab_environment",__DIR__ . "/request/tab_environment/update_tab_environment.php");
$plugin->add_request_action("project_add"           ,__DIR__ . "/request/tab_general_form/project_add.php");
$plugin->add_request_action("project_edit"          ,__DIR__ . "/request/tab_general_form/project_edit.php");
$plugin->add_request_action("project_archive"       ,__DIR__ . "/request/tab_general_form/project_archive.php");
$plugin->add_request_action("project_delete"        ,__DIR__ . "/request/tab_general_form/project_delete.php");

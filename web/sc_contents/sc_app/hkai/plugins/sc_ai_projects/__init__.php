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
sc_ai_projects()->project()->add_reserved_slugs([
    "archive",
    "hkai",
    "docs",
    "index",
    "manifest",
    "projects",
    "request",
    "sc_contents",
    "vendor",
]);

// 登記 health 報告（name＝輸出 JSON key——重複名會即時報錯；request_data＝每輪 health 請求原樣轉交——報告按清單語境過濾）
sc_ai_health()->health()->add_health_report("project", function ($request_data) {
    return sc_ai_projects()->health()->get_project_report($request_data);
});

// 外部貢獻 status item（project 欄——歸檔入口／離開歸檔；provider HTML——Closure 於 render 時求值）
sc_ai_status_bar()->status_bar()->add_status_item("project", function () {
    // 依語境定目標（歸檔語境＝離開歸檔（箭頭圖示）／普通＝入歸檔（盒仔圖示）——後台出；URL 一變由 status bar 區重渲更新）
    if (sc_ai_projects()->project()->is_archive_project_key(sc_get_friendly(0))) {
        $archive_url   = "/";
        $archive_title = hkai__i18n()->get("project.archive_leave");
        $archive_icon  = "fa-solid fa-arrow-left";
    } else {
        $archive_url   = "/archive/";
        $archive_title = hkai__i18n()->get("project.archive");
        $archive_icon  = "fa-solid fa-box-archive";
    }

    return "<a class='sc_statusbar__archive' href='" . $archive_url . "' title='" . $archive_title . "'><i class='" . $archive_icon . "'></i></a>";
});

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
$plugin->add_request_action("git_status"            ,__DIR__ . "/request/tab_git/git_status.php");
$plugin->add_request_action("save_tab_rule"         ,__DIR__ . "/request/tab_rule/save_tab_rule.php");
$plugin->add_request_action("reset_tab_rule"        ,__DIR__ . "/request/tab_rule/reset_tab_rule.php");

$plugin->add_request_action("update_tab_environment",__DIR__ . "/request/tab_environment/update_tab_environment.php");
$plugin->add_request_action("project_add"           ,__DIR__ . "/request/tab_general_form/project_add.php");
$plugin->add_request_action("project_edit"          ,__DIR__ . "/request/tab_general_form/project_edit.php");
$plugin->add_request_action("project_archive"       ,__DIR__ . "/request/tab_general_form/project_archive.php");
$plugin->add_request_action("project_delete"        ,__DIR__ . "/request/tab_general_form/project_delete.php");

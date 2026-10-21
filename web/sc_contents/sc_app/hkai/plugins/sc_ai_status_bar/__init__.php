<?php

// == sc_ai_status_bar plugin：欄底 status bar（三欄渲染＋item） ==
// == 職責：註冊 plugin（asset css）＋載入 bridging＋外部貢獻 status item（sc_ai_diagnostics git 外框）＋health 報告（git——當前專案；sb_git 值來源） ==

// 載入本 plugin bridging（plugin __init__ 先於 cls func 自動載入階段——顯式載入；include_once 確保不重複）
require_once __DIR__ . "/cls/_.func.php";

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, [
    // plugin settings
    "asset" => [
        __DIR__ . "/asset/sc_ai_status_bar.css",
    ],
]);
$plugin->setEnabled(true);

// 註冊 request action（status bar 三區動態載入端點——一區一端點；前端 project_fetch／chat_fetch／workspace_fetch 三支各自叫）
$plugin->add_request_action("project_status_bar_fetch",  __DIR__ . "/request/project_status_bar_fetch.php");
$plugin->add_request_action("chat_status_bar_fetch",     __DIR__ . "/request/chat_status_bar_fetch.php");
$plugin->add_request_action("workspace_status_bar_fetch", __DIR__ . "/request/workspace_status_bar_fetch.php");



// 自身貢獻 status item（sb_project——專案名；Closure 於 render 時求值＝頁面情境或 fetch 情境（三端點 render_region_for_page 換入頁面 URL）皆準）
sc_ai_status_bar()->status_bar()->add_status_item("project", function () {
    $project_slug = sc_ai_projects()->project()->get_selected_project_slug();

    // Home 語境（根 URL＝未指定專案段）→ 用固定 home 查（反映現實：空 slug 本身查唔到嘢）
    if ($project_slug === null || $project_slug === "") {
        $project_slug = "home";
    }

    $project_row  = sc_ai_projects()->project()->get_project_row($project_slug);

    // 判定：查唔到專案 → 維持預設「—」
    if ($project_row === null) {
        return ["id" => "sb_project", "icon" => "📁", "text" => "—", "priority" => 10];
    }

    return ["id" => "sb_project", "icon" => "📁", "text" => $project_row["project_name"], "priority" => 10];
});

// 自身貢獻 status item（sb_chat——對話標題；未選／查唔到＝未選擇對話（對齊 sync_status_items.js 現值））
sc_ai_status_bar()->status_bar()->add_status_item("chat", function () {
    $chat_sid = sc_get_friendly(1);

    // 判定：URL 冇對話段（null）→ 空字串
    if ($chat_sid === null) {
        $chat_sid = "";
    }

    $chat_title    = hkai__i18n()->get("status_bar.no_chat");
    $bare_chat_sid = sc_ai_chats()->chat()->get_bare_chat_sid($chat_sid);
    $chat_row      = sc_ai_chats()->chat()->get_chat_row_by_sid($bare_chat_sid);

    // 判定：查到對話列 → 用對話標題
    if ($chat_row !== null) {
        $chat_title = $chat_row["chat_title"];
    }

    return ["id" => "sb_chat", "icon" => "💬", "text" => $chat_title, "priority" => 10];
});

// 外部貢獻 status item（Git——由 sc_ai_diagnostics 提供 item HTML；Closure 於 render 時求值）
sc_ai_status_bar()->status_bar()->add_status_item("workspace/left", function () {
    return sc_ai_diagnostics()->git()->get_git_status_item();
});

// 外部貢獻 status item（版權——讀 sc_config/hkai/about_hkai.php 嘅 copyright（唯一來源）；擺 workspace/right 末位）
sc_ai_status_bar()->status_bar()->add_status_item("workspace/right", function () {
    // about 資料源（唯一來源——render 時才讀，避免 plugin 註冊期依賴未載入嘅 core bridging）
    $about_data      = require hkai__core()->get_about_file();
    $about_copyright = $about_data["copyright"];

    return [
        "id"       => "sb_copyright",
        "icon"     => "",
        "text"     => $about_copyright,
        "priority" => 40,
        // 點擊契約（data-click='about_copyright'—— evt/clicked_about_copyright.js 接）
        "click"    => ["type" => "about_copyright"],
    ];
});

// 登記 health 報告（git——當前專案 git 狀態；status bar 的 sb_git 隨 health 每輪更新）
sc_ai_health()->health()->add_health_report("git", function ($request_data) {
    $project_slug = $request_data["project_slug"] ?? "";

    // 查表用純 slug（歸檔語境形 archive@<project_slug> → @ 後；其餘 → 原值）
    $project_slug = sc_ai_projects()->project()->get_bare_project_slug($project_slug);

    // Home 語境（空 slug）→ 用固定 home 查（呼叫端自己 fallback）
    if ($project_slug === "") {
        $project_slug = "home";
    }

    $project_row  = sc_ai_projects()->project()->get_project_row($project_slug);

    // 判定：無選取專案 → 未選擇（前端顯示 —）
    if ($project_row === null) {
        return ["ok" => false, "error" => hkai__i18n()->get("status_bar.no_project")];
    }

    return sc_ai_diagnostics()->git()->run_git_status($project_row["project_path"]);
});

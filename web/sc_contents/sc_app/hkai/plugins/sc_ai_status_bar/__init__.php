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

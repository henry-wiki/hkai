<?php

// == sc_ai_settings plugin：Settings modal（general／instructions／provider／server／process viewer／about 分頁） ==
// == 職責：註冊 plugin（asset css＋19 個 request action） ==

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, [
    "asset" => [
        __DIR__ . "/asset/sc_ai_settings.css",

        // CodeMirror 5.65.21（純文字編輯器——CDN 同 jQuery／marked 等既有慣例；Instructions 編輯頁用）
        "https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.min.css",
        "https://cdn.jsdelivr.net/npm/codemirror@5.65.21/theme/material-darker.min.css",
        "https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.min.js",
        "https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/markdown/markdown.min.js",
        "https://cdn.jsdelivr.net/npm/codemirror@5.65.21/addon/edit/continuelist.min.js",
    ],
]);
$plugin->setEnabled(true);

// 註冊 request action（server settings——獨立 handler）
$plugin->add_request_action("fetch_server_settings", __DIR__ . "/request/server/fetch_server_settings.php");

// 註冊 request action（server live logs——1s 輪詢：只回 slots.server_log）
$plugin->add_request_action("fetch_server_log", __DIR__ . "/request/server/fetch_server_log.php");

// 註冊 request action（process viewer——後端渲染分頁內容）
$plugin->add_request_action("fetch_process_viewer", __DIR__ . "/request/process/fetch_process_viewer.php");
$plugin->add_request_action("kill_process", __DIR__ . "/request/process/kill_process.php");

// 註冊 request action（server settings test——獨立 handler）
$plugin->add_request_action("settings_test_server", __DIR__ . "/request/server/settings_test_server.php");

// 註冊 request action（instructions settings——fetch 逐 item／save／reset）
$plugin->add_request_action("fetch_system_prompt", __DIR__ . "/request/instructions/fetch_system_prompt.php");
$plugin->add_request_action("fetch_system_env", __DIR__ . "/request/instructions/fetch_system_env.php");
$plugin->add_request_action("settings_save_instructions", __DIR__ . "/request/instructions/settings_save_instructions.php");
$plugin->add_request_action("settings_reset_instructions", __DIR__ . "/request/instructions/settings_reset_instructions.php");

// 註冊 request action（system env——手動更新環境檔：只准重新產生，內容不可編輯）
$plugin->add_request_action("settings_refresh_system_env", __DIR__ . "/request/instructions/settings_refresh_system_env.php");

// 註冊 request action（server start／restart／stop——獨立 handler）
$plugin->add_request_action("settings_start_server", __DIR__ . "/request/server/settings_start_server.php");
$plugin->add_request_action("settings_restart_server", __DIR__ . "/request/server/settings_restart_server.php");
$plugin->add_request_action("settings_stop_server", __DIR__ . "/request/server/settings_stop_server.php");
$plugin->add_request_action("settings_auto_assign_port", __DIR__ . "/request/server/settings_auto_assign_port.php");

// 註冊 request action（load settings——side menu 動態載入）
$plugin->add_request_action("fetch_settings", __DIR__ . "/request/settings/fetch_settings.php");

// 註冊 request action（about——關於 HKAI 分頁）
$plugin->add_request_action("fetch_about", __DIR__ . "/request/about/fetch_about.php");

// 註冊 request action（general——General 分頁＝settings 第一頁）
$plugin->add_request_action("fetch_general", __DIR__ . "/request/general/fetch_general.php");
$plugin->add_request_action("settings_save_general", __DIR__ . "/request/general/settings_save_general.php");

// 註冊 request action（provider settings——一頁列全部 provider）
$plugin->add_request_action("fetch_providers", __DIR__ . "/request/provider/fetch_providers.php");

// 註冊 request action（provider settings——API Token 儲存：寫 .env）
$plugin->add_request_action("settings_save_provider_settings", __DIR__ . "/request/provider/settings_save_provider_settings.php");

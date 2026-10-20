<?php

// == sc_ai_health plugin：心跳／健康狀態（heartbeat and health related） ==
// == 職責：health 端點（心跳／daemon 狀態——前端輪詢的資料來源） ==

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, []);
$plugin->setEnabled(true);

// 心跳設定注入（bridging——class 只收值）
sc_ai_health()->heartbeat()->initial([
    "stale_threshold_seconds" => 15,
]);

// health 輪詢設定注入（bridging——後台集中調節）
// is_enabled：是否允許輪詢（false → 前端收到後停止下一輪）
// interval_seconds：每輪間隔秒數（9 秒——細 interval 對 PHP／curl 成本高）
sc_ai_health()->health()->initial([
    "is_enabled"       => true,
    "interval_seconds" => 13,
]);

// 登記 health 報告（name＝輸出 JSON key；conn＝bridge 連線快查——讀本機心跳檔，零 HTTP）
// 2026-10-02：舊 bridge 報告（HTTP /process/scan/，42–226ms 太重）改為本機快查版——status bar 的連線狀態（sb_conn）來源
sc_ai_health()->health()->add_health_report("conn", function () {
    return hkai__python_bridge_check()->check_is_python_bridge_running();
});

// 登記 health 動作（清潔工——每輪 health 順手剔 process_list 死行；殺程序從此唔改台帳——2026-10-08 改向）
// 協議：命中「心跳新鮮」免現查；uid tmp＋原檔 checksum 比對（防並發——多頁並存各自跑都安全）
sc_ai_health()->health()->add_health_action("sweep_dead_process_lines", function () {
    return hkai__python_bridge_process()->sweep_dead_process_lines();
});

// 註冊 request action（health——GET 健康狀態端點；URL /request/hkai/fetch_health/）
$plugin->add_request_action("fetch_health", __DIR__ . "/request/health/fetch_health.php");

// 註冊 request action（bridge_check——統一快速檢查端點；URL /request/hkai/fetch_bridge_check/）
$plugin->add_request_action("fetch_bridge_check", __DIR__ . "/request/health/fetch_bridge_check.php");

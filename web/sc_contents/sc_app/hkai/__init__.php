<?php

$route      = $this->get_route();
$controller = sc_controller($route);

// 初始化（manifest 設定＋sqlite 連線——app 啟動唯一入口）
hkai()->initial();

// runtime 設定檢查（缺漏 → 寫 runtime log；不中斷流程）
hkai()->runtime()->check();



// 註冊 app 層 request action：i18n（GET 字典——供前端 JS 取同一份語系 JSON）
$controller->register_request_action("i18n", __DIR__ . "/request/i18n.php");

// 註冊 app 層 request action：set_activity／get_activity（頁面活動讀寫——activity 表）
$controller->register_request_action("set_activity", __DIR__ . "/request/set_activity.php");
$controller->register_request_action("get_activity", __DIR__ . "/request/get_activity.php");

// 註冊 app 層 request action：process/bridge 生命週期（啟動／停止／重啟 python server）
$controller->register_request_action("process/bridge/start_server", __DIR__ . "/request/process/bridge/start_server.php");
$controller->register_request_action("process/bridge/stop_server", __DIR__ . "/request/process/bridge/stop_server.php");
$controller->register_request_action("process/bridge/restart_server", __DIR__ . "/request/process/bridge/restart_server.php");

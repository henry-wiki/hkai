<?php

// == sc_stream plugin：狀態串流（PHP 生產事件＋JS 套用——同一域） ==
// == 職責：state_stream／prompt_stream 兩條 SSE route；事件組裝在 cls/、前端套用在 jsmods/ ==

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, []);
$plugin->setEnabled(true);

// 註冊 request action（狀態串流——SSE；URL /request/hkai/state_stream/）
$plugin->add_request_action("state_stream", __DIR__ . "/request/state_stream.php");

// 註冊 request action（草稿串流——SSE；URL /request/hkai/prompt_stream/）
$plugin->add_request_action("prompt_stream", __DIR__ . "/request/prompt_stream.php");

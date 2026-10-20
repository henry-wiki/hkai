<?php

// == sc_ai_diagnostics plugin：唯讀診斷（read-only diagnostics） ==
// == 職責：診斷程式執行入口（run_git_status）＋ status bar item 貢獻（get_status_item） ==

$route      = $this->get_route();
$controller = sc_controller($route);

$plugin = $controller->register_plugin(__DIR__, []);
$plugin->setEnabled(true);
